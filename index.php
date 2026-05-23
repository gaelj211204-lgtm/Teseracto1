<?php
// Aplicación Monolítica: Visualizador de Hipercubo 4D
// Servido a través de PHP

// Puedes incluir lógica de backend aquí si lo necesitas en el futuro.
$app_title = "Hipercubo 4D Interactivo";
$app_version = "1.0";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $app_title; ?></title>
    <style>
        /* CSS Integrado */
        body {
            margin: 0;
            overflow: hidden;
            background-color: #080812; /* Fondo oscuro espacial */
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            touch-action: none; /* Previene el scroll del navegador en móviles al tocar */
        }
        #ui-layer {
            position: absolute;
            top: 20px;
            left: 20px;
            pointer-events: none; /* Permite que los clics pasen a través del UI hacia el canvas */
            z-index: 10;
            background: rgba(15, 15, 25, 0.7);
            padding: 15px 20px;
            border-radius: 10px;
            border: 1px solid #333;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        h1 { margin: 0 0 5px 0; font-size: 1.2rem; color: #00ffcc; text-transform: uppercase; letter-spacing: 1px;}
        p { margin: 0; font-size: 0.85rem; color: #b3b3b3; line-height: 1.5; }
        .badge { display: inline-block; background: #00ffcc; color: #000; padding: 2px 5px; border-radius: 3px; font-size: 0.7rem; font-weight: bold; }
    </style>

    <!-- Import Map para cargar Three.js como módulos nativos (ES Modules) -->
    <script type="importmap">
    {
        "imports": {
            "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
            "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
        }
    }
    </script>
</head>
<body>

    <div id="ui-layer">
        <h1>Teseracto <span class="badge">v<?php echo $app_version; ?></span></h1>
        <p><strong>PC:</strong> Arrastra para rotar el eje 3D. Rueda para zoom.</p>
        <p><strong>Móvil:</strong> Desliza para rotar. Pellizca para zoom.</p>
    </div>

    <!-- Script principal de la aplicación -->
    <script type="module">
        import * as THREE from 'three';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

        // 1. INICIALIZACIÓN DE THREE.JS (Escena, Cámara y Renderizador)
        const scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x080812, 0.08);

        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 100);
        camera.position.z = 5;

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // Optimizado para pantallas Retina y móviles
        document.body.appendChild(renderer.domElement);

        // 2. CONTROLES INTERACTIVOS (Mouse y Táctil)
        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true; // Rotación con inercia suave
        controls.dampingFactor = 0.05;
        controls.enablePan = false;

        // 3. MATEMÁTICAS DEL HIPERCUBO (4 Dimensiones)
        const vertices4D = [];
        // Generamos los 16 vértices de un teseracto (coordenadas: ±1, ±1, ±1, ±1)
        for (let i = 0; i < 16; i++) {
            vertices4D.push([
                (i & 1) ? 1 : -1,
                (i & 2) ? 1 : -1,
                (i & 4) ? 1 : -1,
                (i & 8) ? 1 : -1
            ]);
        }

        const edges = [];
        // Conectamos vértices si difieren en un solo eje (Distancia de Hamming = 1)
        for (let i = 0; i < 16; i++) {
            for (let j = i + 1; j < 16; j++) {
                const diff = i ^ j;
                if (diff === 1 || diff === 2 || diff === 4 || diff === 8) {
                    edges.push([i, j]);
                }
            }
        }

        // 4. CREACIÓN DE GEOMETRÍA EN THREE.JS
        const group = new THREE.Group();
        scene.add(group);

        // Materiales
        const materialLineas = new THREE.LineBasicMaterial({ color: 0x00ffcc, transparent: true, opacity: 0.7, linewidth: 2 });
        const materialNodos = new THREE.MeshBasicMaterial({ color: 0xff0066 });

        // Buffer Geometry para las aristas (optimizado)
        const geometriaLineas = new THREE.BufferGeometry();
        const posicionesCero = new Float32Array(edges.length * 2 * 3);
        geometriaLineas.setAttribute('position', new THREE.BufferAttribute(posicionesCero, 3));
        const lineas = new THREE.LineSegments(geometriaLineas, materialLineas);
        group.add(lineas);

        // Nodos (esferas en las esquinas)
        const nodos = [];
        const geometriaEsfera = new THREE.SphereGeometry(0.06, 16, 16);
        for(let i = 0; i < 16; i++) {
            const esfera = new THREE.Mesh(geometriaEsfera, materialNodos);
            nodos.push(esfera);
            group.add(esfera);
        }

        // 5. MOTOR DE PROYECCIÓN 4D a 3D Y ANIMACIÓN
        let tiempo = 0;

        function proyectar4Da3D() {
            tiempo += 0.01;

            // Rotación automática continua en los planos 4D para que el hipercubo se "desdoble"
            const anguloXW = tiempo * 0.6;
            const anguloZW = tiempo * 0.4;

            const posicionesLineas = lineas.geometry.attributes.position.array;
            let idxLinea = 0;
            const verticesProyectados = [];

            // Rotar y proyectar los 16 vértices
            for (let i = 0; i < 16; i++) {
                let [x, y, z, w] = vertices4D[i];

                // Aplicar matriz de rotación 4D en el plano XW
                let x1 = x * Math.cos(anguloXW) - w * Math.sin(anguloXW);
                let w1 = x * Math.sin(anguloXW) + w * Math.cos(anguloXW);

                // Aplicar matriz de rotación 4D en el plano ZW
                let z1 = z * Math.cos(anguloZW) - w1 * Math.sin(anguloZW);
                let w2 = z * Math.sin(anguloZW) + w1 * Math.cos(anguloZW);

                // Proyección en perspectiva de 4D a 3D
                const distanciaProyeccion = 2.5; 
                const w_factor = 1 / (distanciaProyeccion - w2);

                const xProy = x1 * w_factor * 2;
                const yProy = y * w_factor * 2;
                const zProy = z1 * w_factor * 2;

                verticesProyectados.push([xProy, yProy, zProy]);

                // Actualizar la posición de los nodos (esferitas)
                nodos[i].position.set(xProy, yProy, zProy);
            }

            // Actualizar la posición de las líneas conectando los nuevos vértices proyectados
            for (let i = 0; i < edges.length; i++) {
                const v1 = verticesProyectados[edges[i][0]];
                const v2 = verticesProyectados[edges[i][1]];

                posicionesLineas[idxLinea++] = v1[0];
                posicionesLineas[idxLinea++] = v1[1];
                posicionesLineas[idxLinea++] = v1[2];

                posicionesLineas[idxLinea++] = v2[0];
                posicionesLineas[idxLinea++] = v2[1];
                posicionesLineas[idxLinea++] = v2[2];
            }

            lineas.geometry.attributes.position.needsUpdate = true;
        }

        // 6. BUCLE PRINCIPAL DE RENDERIZADO
        function animar() {
            requestAnimationFrame(animar);
            proyectar4Da3D(); // Calcula la nueva forma 4D
            controls.update(); // Actualiza la rotación 3D del mouse/táctil
            renderer.render(scene, camera);
        }
        animar();

        // 7. RESPONSIVE: Adaptación a cualquier resolución y rotación de pantalla
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    </script>
</body>
</html>