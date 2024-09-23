// Set up the scene, camera, and renderer
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
const renderer = new THREE.WebGLRenderer({ canvas: document.getElementById('galaxy-background'), antialias: true });
renderer.setSize(window.innerWidth, window.innerHeight);

// Galaxy parameters
const galaxyParams = {
    starsCount: 200,
    radius: 10,
    height: 50,
    turns: 8,
    branches: 5,
    spin: 1,
    randomness: 0.2,
    randomnessPower: 3,
    insideColor: 0xff6030,
    outsideColor: 0x1b3984
};

// Create galaxy geometry
const galaxyGeometry = new THREE.BufferGeometry();
const galaxyMaterial = new THREE.PointsMaterial({
    size: 0.1,
    sizeAttenuation: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    vertexColors: true
});

// Generate star positions and colors
const positions = new Float32Array(galaxyParams.starsCount * 3);
const colors = new Float32Array(galaxyParams.starsCount * 3);

for (let i = 0; i < galaxyParams.starsCount; i++) {
    const i3 = i * 3;

    // Position
    const radius = Math.random() * galaxyParams.radius;
    const spinAngle = radius * galaxyParams.spin;
    const branchAngle = ((i % galaxyParams.branches) / galaxyParams.branches) * Math.PI * 2;
    
    const randomX = Math.pow(Math.random(), galaxyParams.randomnessPower) * (Math.random() < 0.5 ? 1 : -1) * galaxyParams.randomness * radius;
    const randomY = Math.pow(Math.random(), galaxyParams.randomnessPower) * (Math.random() < 0.5 ? 1 : -1) * galaxyParams.randomness * radius;
    const randomZ = Math.pow(Math.random(), galaxyParams.randomnessPower) * (Math.random() < 0.5 ? 1 : -1) * galaxyParams.randomness * radius;

    positions[i3] = Math.cos(branchAngle + spinAngle) * radius + randomX;
    positions[i3 + 1] = randomY;
    positions[i3 + 2] = Math.sin(branchAngle + spinAngle) * radius + randomZ;

    // Color
    const mixedColor = new THREE.Color(galaxyParams.insideColor);
    mixedColor.lerp(new THREE.Color(galaxyParams.outsideColor), radius / galaxyParams.radius);

    colors[i3] = mixedColor.r;
    colors[i3 + 1] = mixedColor.g;
    colors[i3 + 2] = mixedColor.b;
}

galaxyGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
galaxyGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

// Create the galaxy points
const galaxy = new THREE.Points(galaxyGeometry, galaxyMaterial);
scene.add(galaxy);

// Position the camera
camera.position.z = 80;
camera.position.y = 40;
camera.lookAt(0, 0, 0);

// Animation
const clock = new THREE.Clock();

function animate() {
    const elapsedTime = clock.getElapsedTime();
    
    // Rotate the entire galaxy
    galaxy.rotation.y = elapsedTime * 0.05;
    
    // Create a swirling effect
    const positions = galaxy.geometry.attributes.position.array;
    for (let i = 0; i < positions.length; i += 3) {
        const x = positions[i];
        const y = positions[i + 1];
        const z = positions[i + 2];
        
        const distance = Math.sqrt(x * x + z * z);
        const angle = Math.atan2(z, x) + (elapsedTime * 0.1 * (1 - distance / galaxyParams.radius));
        
        positions[i] = Math.cos(angle) * distance;
        positions[i + 2] = Math.sin(angle) * distance;
        
        // Add vertical motion
        positions[i + 1] = y + Math.sin(elapsedTime + distance * 0.2) * 0.5;
    }
    galaxy.geometry.attributes.position.needsUpdate = true;

    renderer.render(scene, camera);
    requestAnimationFrame(animate);
}

// Start the animation
animate();

// Handle window resizing
window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});