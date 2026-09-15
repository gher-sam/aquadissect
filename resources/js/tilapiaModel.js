import * as THREE from 'three';
import { CSS2DObject } from 'three/addons/renderers/CSS2DRenderer.js';
import { anatomy } from './lessonData.js';

const FRONT_Z = 0.72;

function standardMaterial(color, options = {}) {
  return new THREE.MeshStandardMaterial({
    color,
    roughness: 0.66,
    metalness: 0.02,
    transparent: options.transparent ?? false,
    opacity: options.opacity ?? 1,
    side: options.side ?? THREE.FrontSide,
    depthWrite: options.depthWrite ?? true,
    emissive: options.emissive ?? '#000000',
    emissiveIntensity: 0,
  });
}

function shapeMesh(points, material, depth = 0.035) {
  const shape = new THREE.Shape();
  points.forEach(([x, y], index) => {
    if (index === 0) shape.moveTo(x, y);
    else shape.lineTo(x, y);
  });
  shape.closePath();
  const geometry = new THREE.ExtrudeGeometry(shape, {
    depth,
    bevelEnabled: true,
    bevelSize: 0.025,
    bevelThickness: 0.018,
    bevelSegments: 2,
  });
  geometry.center();
  return new THREE.Mesh(geometry, material);
}

function organMesh(geometry, color, scale, position) {
  const mesh = new THREE.Mesh(geometry, standardMaterial(color));
  mesh.scale.set(...scale);
  mesh.position.set(...position);
  mesh.castShadow = true;
  mesh.receiveShadow = true;
  return mesh;
}

function addFinRays(fin, segments) {
  const points = segments.flatMap(([from, to]) => [new THREE.Vector3(...from), new THREE.Vector3(...to)]);
  const geometry = new THREE.BufferGeometry().setFromPoints(points);
  const rays = new THREE.LineSegments(
    geometry,
    new THREE.LineBasicMaterial({ color: '#332f2c', transparent: true, opacity: 0.38 }),
  );
  rays.position.z = 0.065;
  fin.add(rays);
}

export class TilapiaModel {
  constructor(scene) {
    this.scene = scene;
    this.root = new THREE.Group();
    this.root.rotation.x = -0.03;
    this.scene.add(this.root);

    this.parts = new Map();
    this.selectable = [];
    this.labelsAll = false;
    this.highlighted = new Set();
    this.selectedId = null;
    this.step = 0;
    this.explode = 0;
    this.explodeTarget = 0;
    this.flapOpen = 0;
    this.flapTarget = 0;
    this.incision = 0;
    this.incisionTarget = 0;

    this.externalIds = [
      'eye',
      'operculum',
      'lateralLine',
      'dorsalFin',
      'caudalFin',
      'pectoralFin',
      'pelvicFin',
      'analFin',
      'vent',
    ];
    this.internalIds = [
      'gills',
      'heart',
      'liver',
      'stomach',
      'intestine',
      'swimBladder',
      'kidney',
      'gonad',
      'spine',
    ];

    this.buildEnvironment();
    this.buildBody();
    this.buildExternalParts();
    this.buildCavity();
    this.buildInternalParts();
    this.setStep(0);
  }

  buildEnvironment() {
    const shadow = new THREE.Mesh(
      new THREE.PlaneGeometry(9.5, 4.8),
      new THREE.ShadowMaterial({ color: '#053845', opacity: 0.12 }),
    );
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.set(0, -1.75, 0);
    shadow.receiveShadow = true;
    this.root.add(shadow);

    const bubbleMaterial = new THREE.MeshBasicMaterial({
      color: '#9eece9',
      transparent: true,
      opacity: 0.22,
      wireframe: true,
    });
    const bubblePositions = [
      [-3.6, 1.65, -1.4, 0.12],
      [-3.25, 2.05, -1.1, 0.07],
      [3.55, 1.3, -1.25, 0.16],
      [3.85, 1.82, -1.4, 0.09],
      [2.9, -1.25, -1.1, 0.07],
    ];
    bubblePositions.forEach(([x, y, z, size]) => {
      const bubble = new THREE.Mesh(new THREE.SphereGeometry(size, 14, 10), bubbleMaterial);
      bubble.position.set(x, y, z);
      bubble.userData.bubbleOrigin = y;
      bubble.userData.bubbleSpeed = 0.25 + size;
      this.root.add(bubble);
    });
  }

  buildBody() {
    this.bodyMaterial = standardMaterial('#7b8881', {
      transparent: true,
      opacity: 1,
      side: THREE.DoubleSide,
    });
    this.bodyMaterial.roughness = 0.55;

    const bodyShape = new THREE.Shape();
    bodyShape.moveTo(-3.18, -0.08);
    bodyShape.bezierCurveTo(-3.04, 0.56, -2.62, 0.98, -1.82, 1.25);
    bodyShape.bezierCurveTo(-0.8, 1.58, 0.5, 1.5, 1.38, 1.13);
    bodyShape.bezierCurveTo(2.02, 0.86, 2.34, 0.5, 2.62, 0.3);
    bodyShape.bezierCurveTo(2.54, 0.1, 2.54, -0.14, 2.62, -0.35);
    bodyShape.bezierCurveTo(2.1, -0.56, 1.64, -0.95, 0.76, -1.18);
    bodyShape.bezierCurveTo(-0.42, -1.49, -1.75, -1.25, -2.5, -0.82);
    bodyShape.bezierCurveTo(-2.94, -0.57, -3.18, -0.28, -3.18, -0.08);

    const bodyGeometry = new THREE.ExtrudeGeometry(bodyShape, {
      depth: 0.72,
      bevelEnabled: true,
      bevelSize: 0.17,
      bevelThickness: 0.18,
      bevelSegments: 5,
      curveSegments: 28,
    });
    bodyGeometry.center();
    bodyGeometry.computeBoundingBox();
    const bounds = bodyGeometry.boundingBox;
    const positions = bodyGeometry.attributes.position;
    const planarUvs = new Float32Array(positions.count * 2);
    const width = bounds.max.x - bounds.min.x;
    const height = bounds.max.y - bounds.min.y;
    for (let index = 0; index < positions.count; index += 1) {
      planarUvs[index * 2] = (positions.getX(index) - bounds.min.x) / width;
      planarUvs[index * 2 + 1] = (positions.getY(index) - bounds.min.y) / height;
    }
    bodyGeometry.setAttribute('uv', new THREE.BufferAttribute(planarUvs, 2));
    this.body = new THREE.Mesh(bodyGeometry, this.bodyMaterial);
    this.body.position.set(-0.04, 0.02, 0);
    this.body.castShadow = true;
    this.body.receiveShadow = true;
    this.root.add(this.body);

    const bellyGlow = shapeMesh(
      [[-2.3, 0.2], [-1.6, -0.42], [-0.3, -0.62], [1.18, -0.42], [2.0, 0.08], [1.2, -0.64], [-0.5, -0.9], [-1.8, -0.58]],
      standardMaterial('#d7d4c6', { transparent: true, opacity: 0.26, side: THREE.DoubleSide }),
      0.018,
    );
    bellyGlow.position.set(-0.05, -0.47, 0.56);
    this.root.add(bellyGlow);

    const mouthMaterial = standardMaterial('#594b47');
    const upperMouth = new THREE.Mesh(
      new THREE.TubeGeometry(new THREE.LineCurve3(
        new THREE.Vector3(-3.2, -0.08, 0.42),
        new THREE.Vector3(-2.86, -0.02, 0.55),
      ), 16, 0.025, 8, false),
      mouthMaterial,
    );
    const lowerMouth = new THREE.Mesh(
      new THREE.TubeGeometry(new THREE.LineCurve3(
        new THREE.Vector3(-3.18, -0.12, 0.42),
        new THREE.Vector3(-2.9, -0.22, 0.54),
      ), 16, 0.022, 8, false),
      mouthMaterial,
    );
    const nostril = new THREE.Mesh(new THREE.SphereGeometry(0.035, 12, 10), standardMaterial('#263431'));
    nostril.position.set(-2.7, 0.3, 0.55);
    this.root.add(upperMouth, lowerMouth, nostril);
  }

  buildExternalParts() {
    const finMaterial = standardMaterial('#48544f', {
      transparent: true,
      opacity: 0.72,
      side: THREE.DoubleSide,
    });

    const dorsal = shapeMesh(
      [[-1.7, -0.08], [-1.42, 0.63], [-0.75, 0.84], [0.2, 0.8], [1.35, 0.36], [1.68, -0.08]],
      finMaterial.clone(),
    );
    addFinRays(dorsal, [
      [[-1.35, -0.06, 0], [-1.18, 0.56, 0]],
      [[-0.9, -0.06, 0], [-0.72, 0.72, 0]],
      [[-0.42, -0.06, 0], [-0.26, 0.76, 0]],
      [[0.08, -0.06, 0], [0.2, 0.68, 0]],
      [[0.58, -0.06, 0], [0.68, 0.52, 0]],
      [[1.05, -0.06, 0], [1.16, 0.3, 0]],
    ]);
    dorsal.position.set(0.12, 1.37, -0.02);
    this.root.add(dorsal);
    this.registerPart('dorsalFin', dorsal, [0, 0.9, -0.1], [0, 0.28, 0]);

    const tail = shapeMesh(
      [[-0.12, 0], [0.72, 0.92], [1.13, 0.78], [1.06, 0], [1.13, -0.78], [0.72, -0.92]],
      finMaterial.clone(),
    );
    tail.material.color.set('#675b58');
    addFinRays(tail, [
      [[-0.52, 0, 0], [0.5, 0.72, 0]],
      [[-0.52, 0, 0], [0.58, 0.46, 0]],
      [[-0.52, 0, 0], [0.58, 0.17, 0]],
      [[-0.52, 0, 0], [0.58, -0.17, 0]],
      [[-0.52, 0, 0], [0.58, -0.46, 0]],
      [[-0.52, 0, 0], [0.5, -0.72, 0]],
    ]);
    tail.position.set(3.18, 0, 0.01);
    tail.scale.set(1.32, 1.08, 1);
    this.root.add(tail);
    this.registerPart('caudalFin', tail, [1.1, 0.15, -0.2], [0.56, 0.15, 0]);

    const pectoral = shapeMesh(
      [[-0.16, 0.04], [0.66, -0.12], [0.88, -0.56], [0.3, -0.43]],
      standardMaterial('#756c5d', { transparent: true, opacity: 0.82, side: THREE.DoubleSide }),
    );
    addFinRays(pectoral, [
      [[-0.34, 0.18, 0], [0.36, -0.28, 0]],
      [[-0.28, 0.12, 0], [0.42, -0.12, 0]],
      [[-0.22, 0.05, 0], [0.3, 0.04, 0]],
    ]);
    pectoral.position.set(-1.35, -0.08, 0.56);
    pectoral.scale.set(0.78, 0.78, 0.78);
    pectoral.rotation.z = -0.18;
    this.root.add(pectoral);
    this.registerPart('pectoralFin', pectoral, [-0.25, -0.72, 0.65], [0.42, -0.22, 0.02]);

    const pelvic = shapeMesh(
      [[-0.42, 0.1], [0.5, -0.12], [0.74, -0.52], [0.12, -0.38]],
      finMaterial.clone(),
    );
    pelvic.position.set(-0.42, -1.18, 0.18);
    pelvic.scale.set(0.78, 0.72, 0.78);
    this.root.add(pelvic);
    this.registerPart('pelvicFin', pelvic, [-0.2, -0.8, 0.1], [0.35, -0.18, 0]);

    const anal = shapeMesh(
      [[-0.8, 0.16], [0.5, 0.12], [0.78, -0.28], [-0.4, -0.42]],
      finMaterial.clone(),
    );
    anal.position.set(1.35, -1.19, 0.02);
    anal.scale.set(0.88, 0.82, 0.88);
    this.root.add(anal);
    this.registerPart('analFin', anal, [0.3, -0.8, -0.1], [0.4, -0.18, 0]);

    const eyeGroup = new THREE.Group();
    eyeGroup.position.set(-2.43, 0.48, 0.53);
    const eye = new THREE.Mesh(new THREE.SphereGeometry(0.145, 24, 18), standardMaterial('#d9c89e'));
    eye.scale.z = 0.45;
    const pupil = new THREE.Mesh(new THREE.SphereGeometry(0.076, 20, 14), standardMaterial('#111918'));
    pupil.position.z = 0.115;
    pupil.scale.z = 0.55;
    const catchlight = new THREE.Mesh(new THREE.SphereGeometry(0.021, 12, 10), new THREE.MeshBasicMaterial({ color: '#ffffff' }));
    catchlight.position.set(-0.022, 0.028, 0.156);
    eyeGroup.add(eye, pupil, catchlight);
    this.root.add(eyeGroup);
    this.registerPart('eye', eyeGroup, [-0.65, 0.42, 0.42], [0, 0.22, 0]);

    const operculum = new THREE.Mesh(
      new THREE.TorusGeometry(0.5, 0.027, 10, 48, Math.PI * 1.45),
      standardMaterial('#4a5d58'),
    );
    operculum.position.set(-1.87, 0.02, 0.54);
    operculum.scale.set(0.82, 1.18, 1);
    operculum.rotation.z = -Math.PI * 0.76;
    this.root.add(operculum);
    this.registerPart('operculum', operculum, [-0.55, 0.05, 0.72], [0.03, 0.48, 0]);
    this.operculum = operculum;

    const lateralCurve = new THREE.CatmullRomCurve3([
      new THREE.Vector3(-1.52, 0.17, 0.69),
      new THREE.Vector3(-0.5, 0.12, 0.71),
      new THREE.Vector3(0.65, 0.14, 0.68),
      new THREE.Vector3(1.8, 0.24, 0.55),
      new THREE.Vector3(2.38, 0.2, 0.36),
    ]);
    const lateralLine = new THREE.Mesh(
      new THREE.TubeGeometry(lateralCurve, 64, 0.018, 7, false),
      standardMaterial('#65736c'),
    );
    this.root.add(lateralLine);
    this.registerPart('lateralLine', lateralLine, [0.15, 0.5, 0.75], [0.8, 0.22, 0]);

    const vent = new THREE.Mesh(
      new THREE.TorusGeometry(0.055, 0.016, 8, 24),
      standardMaterial('#b36c5a'),
    );
    vent.position.set(1.42, -0.88, 0.48);
    this.root.add(vent);
    this.registerPart('vent', vent, [0.4, -0.7, 0.4], [0, -0.18, 0]);
  }

  buildCavity() {
    const cavityMaterial = standardMaterial('#6f3b40', {
      transparent: true,
      opacity: 0.46,
      side: THREE.DoubleSide,
    });
    this.cavity = shapeMesh(
      [[-1.45, -0.72], [-1.28, 0.6], [-0.5, 0.82], [0.72, 0.78], [1.55, 0.38], [1.45, -0.66], [0.25, -0.92]],
      cavityMaterial,
      0.02,
    );
    this.cavity.position.set(0, -0.03, 0.56);
    this.root.add(this.cavity);

    this.flap = shapeMesh(
      [[-1.46, -0.72], [-1.25, 0.58], [-0.48, 0.79], [0.72, 0.74], [1.52, 0.35], [1.42, -0.64], [0.24, -0.88]],
      standardMaterial('#7c948c', { transparent: true, opacity: 0.42, side: THREE.DoubleSide }),
      0.055,
    );
    this.flap.position.set(0, -0.03, 0.73);
    this.flap.scale.set(0.92, 0.92, 0.92);
    this.flap.castShadow = true;
    this.root.add(this.flap);

    const incisionCurve = new THREE.LineCurve3(
      new THREE.Vector3(1.43, -0.84, 0.82),
      new THREE.Vector3(-1.42, -0.84, 0.82),
    );
    this.incisionMesh = new THREE.Mesh(
      new THREE.TubeGeometry(incisionCurve, 80, 0.035, 8, false),
      new THREE.MeshBasicMaterial({ color: '#ffc857', transparent: true, opacity: 0.95 }),
    );
    this.incisionMesh.geometry.setDrawRange(0, 0);
    this.root.add(this.incisionMesh);
  }

  buildInternalParts() {
    const gills = new THREE.Group();
    gills.position.set(-1.78, 0.02, FRONT_Z);
    for (let index = 0; index < 6; index += 1) {
      const filament = new THREE.Mesh(
        new THREE.TorusGeometry(0.38 - index * 0.025, 0.028, 8, 26, Math.PI * 1.15),
        standardMaterial(index % 2 === 0 ? '#d84d55' : '#ed6a67'),
      );
      filament.position.set(index * 0.055, index * 0.025 - 0.08, index * 0.012);
      filament.scale.set(0.72, 1.15, 1);
      filament.rotation.z = -0.65;
      gills.add(filament);
    }
    this.root.add(gills);
    this.registerPart('gills', gills, [-1.0, 0.2, 0.9], [0, 0.52, 0]);

    const heart = organMesh(new THREE.SphereGeometry(1, 28, 20), '#c63e48', [0.25, 0.34, 0.18], [-1.23, -0.58, FRONT_Z]);
    heart.rotation.z = -0.2;
    this.root.add(heart);
    this.registerPart('heart', heart, [-0.65, -0.75, 0.72], [0, 0.42, 0]);

    const liver = organMesh(new THREE.SphereGeometry(1, 36, 24), '#8e4d3f', [0.75, 0.46, 0.18], [-0.62, -0.27, FRONT_Z - 0.02]);
    liver.rotation.z = 0.12;
    this.root.add(liver);
    this.registerPart('liver', liver, [-0.72, -0.42, 1.1], [-0.1, 0.55, 0]);

    const stomach = organMesh(new THREE.SphereGeometry(1, 30, 20), '#d98972', [0.58, 0.34, 0.15], [0.23, -0.3, FRONT_Z + 0.01]);
    stomach.rotation.z = -0.35;
    this.root.add(stomach);
    this.registerPart('stomach', stomach, [0.05, -0.95, 1.25], [0.1, 0.4, 0]);

    const intestineCurve = new THREE.CatmullRomCurve3([
      new THREE.Vector3(-0.1, -0.55, 0),
      new THREE.Vector3(0.55, -0.7, 0),
      new THREE.Vector3(1.1, -0.48, 0),
      new THREE.Vector3(0.5, -0.32, 0),
      new THREE.Vector3(0.05, -0.58, 0),
      new THREE.Vector3(0.68, -0.77, 0),
      new THREE.Vector3(1.28, -0.65, 0),
    ]);
    const intestine = new THREE.Mesh(
      new THREE.TubeGeometry(intestineCurve, 72, 0.075, 10, false),
      standardMaterial('#e8b88d'),
    );
    intestine.position.z = FRONT_Z + 0.04;
    intestine.castShadow = true;
    this.root.add(intestine);
    this.registerPart('intestine', intestine, [0.55, -1.05, 1.1], [0.45, 0.18, 0]);

    const swimBladder = organMesh(
      new THREE.CapsuleGeometry(0.22, 1.7, 8, 18),
      '#bfe6df',
      [0.82, 1, 0.52],
      [0.45, 0.5, FRONT_Z - 0.12],
    );
    swimBladder.rotation.z = Math.PI / 2;
    swimBladder.material.transparent = true;
    swimBladder.material.opacity = 0.88;
    this.root.add(swimBladder);
    this.registerPart('swimBladder', swimBladder, [0.35, 1.0, 0.85], [0.15, 0.34, 0]);

    const kidney = organMesh(
      new THREE.CapsuleGeometry(0.11, 2.15, 6, 16),
      '#613f4b',
      [0.75, 1, 0.5],
      [0.37, 0.83, FRONT_Z - 0.22],
    );
    kidney.rotation.z = Math.PI / 2;
    this.root.add(kidney);
    this.registerPart('kidney', kidney, [0.28, 1.5, 0.52], [0.2, 0.28, 0]);

    const gonad = organMesh(
      new THREE.CapsuleGeometry(0.15, 1.0, 6, 16),
      '#e0bd68',
      [0.74, 1, 0.6],
      [0.84, 0.02, FRONT_Z + 0.02],
    );
    gonad.rotation.z = Math.PI / 2;
    this.root.add(gonad);
    this.registerPart('gonad', gonad, [1.2, 0.4, 0.95], [0.1, 0.35, 0]);

    const spine = new THREE.Group();
    spine.position.set(0.25, 0.7, 0.2);
    const centrumMaterial = standardMaterial('#e6dfc8');
    for (let index = 0; index < 18; index += 1) {
      const x = -1.85 + index * 0.23;
      const centrum = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.075, 0.16, 10), centrumMaterial);
      centrum.rotation.z = Math.PI / 2;
      centrum.position.x = x;
      spine.add(centrum);
      if (index > 1 && index < 15 && index % 2 === 0) {
        const rib = new THREE.Mesh(
          new THREE.TorusGeometry(0.34, 0.018, 6, 18, Math.PI * 0.75),
          centrumMaterial,
        );
        rib.position.set(x, -0.1, 0);
        rib.rotation.z = -Math.PI * 0.88;
        rib.scale.y = 1.45;
        spine.add(rib);
      }
    }
    this.root.add(spine);
    this.registerPart('spine', spine, [0, 1.85, -0.25], [0.1, 0.32, 0]);
  }

  registerPart(id, object, explodeOffset, labelOffset) {
    object.userData.partId = id;
    object.userData.basePosition = object.position.clone();
    object.userData.explodeOffset = new THREE.Vector3(...explodeOffset);
    object.traverse((child) => {
      child.userData.partId = id;
      if (child.isMesh) {
        child.castShadow = true;
        child.userData.baseEmissiveIntensity = child.material?.emissiveIntensity ?? 0;
        this.selectable.push(child);
      }
    });

    const labelElement = document.createElement('div');
    labelElement.className = 'model-label';
    labelElement.innerHTML = `<span aria-hidden="true"></span>${anatomy[id].name}`;
    const label = new CSS2DObject(labelElement);
    label.position.set(...labelOffset);
    label.visible = false;
    object.add(label);

    this.parts.set(id, { object, label, element: labelElement });
  }

  setStep(step) {
    this.step = step;
    const cavityVisible = step >= 3;
    this.cavity.visible = cavityVisible;
    this.flap.visible = cavityVisible;
    this.incisionMesh.visible = step === 3;

    this.internalIds.forEach((id) => {
      const entry = this.parts.get(id);
      if (!entry) return;
      entry.object.visible = id === 'gills' ? step >= 2 : step >= 4;
    });

    this.bodyOpacityTarget = step >= 4 ? 0.16 : step === 3 ? 0.78 : 1;
    this.flapTarget = step >= 4 ? 1 : 0;
    if (step < 3) this.incisionTarget = 0;
    this.explodeTarget = step === 7 ? 0.58 : 0;
    this.updateLabelVisibility();
  }

  traceIncision() {
    this.incisionTarget = 1;
  }

  openBodyWall() {
    this.flapTarget = 1;
    this.bodyOpacityTarget = 0.16;
    this.internalIds.forEach((id) => {
      const entry = this.parts.get(id);
      if (entry) entry.object.visible = true;
    });
  }

  setExplode(value) {
    this.explodeTarget = THREE.MathUtils.clamp(value, 0, 1);
  }

  setLabelsVisible(visible) {
    this.labelsAll = visible;
    this.updateLabelVisibility();
  }

  setHighlighted(ids) {
    this.highlighted = new Set(ids);
    this.updateMaterials();
    this.updateLabelVisibility();
  }

  selectPart(id) {
    this.selectedId = id;
    this.updateMaterials();
    this.updateLabelVisibility();
  }

  updateMaterials() {
    this.parts.forEach(({ object }, id) => {
      const isHighlighted = this.highlighted.has(id);
      const isSelected = this.selectedId === id;
      object.traverse((child) => {
        if (!child.isMesh || !child.material) return;
        if ('emissiveIntensity' in child.material) {
          child.material.emissive.set(isSelected ? '#f6c85f' : isHighlighted ? '#48e5d1' : '#000000');
          child.material.emissiveIntensity = isSelected ? 0.5 : isHighlighted ? 0.24 : 0;
        }
      });
    });
  }

  updateLabelVisibility() {
    this.parts.forEach(({ object, label }, id) => {
      const shouldShow = object.visible && (
        this.labelsAll || this.highlighted.has(id) || this.selectedId === id
      );
      label.visible = shouldShow;
    });
  }

  getPartId(intersectionObject) {
    let current = intersectionObject;
    while (current) {
      if (current.userData?.partId) return current.userData.partId;
      current = current.parent;
    }
    return null;
  }

  getPartPosition(id) {
    const entry = this.parts.get(id);
    if (!entry) return new THREE.Vector3();
    return entry.object.getWorldPosition(new THREE.Vector3());
  }

  update(delta, elapsed) {
    const speed = this.reduceMotion ? 1 : Math.min(1, delta * 5.5);
    this.explode = THREE.MathUtils.lerp(this.explode, this.explodeTarget, speed);
    this.flapOpen = THREE.MathUtils.lerp(this.flapOpen, this.flapTarget, speed);
    this.incision = THREE.MathUtils.lerp(this.incision, this.incisionTarget, this.reduceMotion ? 1 : Math.min(1, delta * 3.2));
    this.bodyMaterial.opacity = THREE.MathUtils.lerp(
      this.bodyMaterial.opacity,
      this.bodyOpacityTarget ?? 0.94,
      speed,
    );

    this.parts.forEach(({ object }) => {
      const base = object.userData.basePosition;
      const offset = object.userData.explodeOffset;
      if (!base || !offset) return;
      object.position.set(
        base.x + offset.x * this.explode,
        base.y + offset.y * this.explode,
        base.z + offset.z * this.explode,
      );
    });

    this.flap.position.set(
      0.15 + this.flapOpen * 0.22,
      -0.03 - this.flapOpen * 1.24,
      0.73 + this.flapOpen * 0.34,
    );
    this.flap.rotation.set(this.flapOpen * 0.15, -this.flapOpen * 0.85, this.flapOpen * 0.05);
    this.flap.material.opacity = 0.97 - this.flapOpen * 0.12;

    const indexCount = this.incisionMesh.geometry.index?.count ?? 0;
    this.incisionMesh.geometry.setDrawRange(0, Math.floor(indexCount * this.incision));

    this.root.children.forEach((child) => {
      if (child.userData.bubbleOrigin == null) return;
      child.position.y = child.userData.bubbleOrigin + Math.sin(elapsed * child.userData.bubbleSpeed) * 0.12;
    });
  }
}
