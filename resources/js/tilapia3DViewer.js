import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { CSS2DRenderer } from 'three/addons/renderers/CSS2DRenderer.js';
import { gsap } from 'gsap';
import { TilapiaModel } from './tilapiaModel.js';

const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

export class Tilapia3DViewer {
  constructor(container, anatomy, { onSelect, onDepthChange } = {}) {
    this.container = container;
    this.anatomy = anatomy;
    this.onSelect = onSelect;
    this.onDepthChange = onDepthChange;
    this.depth = 0;
    this.step = 0;
    this.playing = false;
    this.pointerDown = null;
    this.lastFrame = performance.now();
    this.elapsed = 0;
    this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.raycaster = new THREE.Raycaster();
    this.pointer = new THREE.Vector2();

    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color('#edf8f6');
    this.scene.fog = new THREE.Fog('#edf8f6', 13, 23);

    this.camera = new THREE.PerspectiveCamera(32, 1, 0.1, 80);
    this.defaultCameraZ = 14.5;
    this.userAdjustedCamera = false;
    this.camera.position.set(0.1, 0.15, this.defaultCameraZ);

    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.2;
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFShadowMap;
    this.renderer.domElement.className = 'tilapia-canvas';
    this.renderer.domElement.setAttribute('role', 'img');
    this.renderer.domElement.setAttribute('aria-label', 'Interactive three-dimensional Tilapia anatomy model');
    container.appendChild(this.renderer.domElement);

    this.labelRenderer = new CSS2DRenderer();
    this.labelRenderer.domElement.className = 'label-layer';
    this.labelRenderer.domElement.setAttribute('aria-hidden', 'true');
    container.appendChild(this.labelRenderer.domElement);

    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.dampingFactor = 0.075;
    this.controls.enablePan = false;
    this.controls.minDistance = 8.5;
    this.controls.maxDistance = 24;
    this.controls.minPolarAngle = Math.PI * 0.23;
    this.controls.maxPolarAngle = Math.PI * 0.77;
    this.controls.target.set(0, 0, 0);
    this.controls.addEventListener('start', () => {
      gsap.killTweensOf([this.camera.position, this.controls.target]);
      this.userAdjustedCamera = true;
    });

    this.buildStudio();
    this.model = new TilapiaModel(this.scene);
    this.externalIds = this.model.externalIds;
    this.internalIds = this.model.internalIds;
    this.enhanceMaterials();
    this.createSwimMotion();
    this.bindInteractions();
    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(container);
    this.resize();
    this.setStep(0);

    this.ready = new Promise((resolve) => {
      requestAnimationFrame(() => {
        this.render();
        resolve();
      });
    });
    this.animationFrame = requestAnimationFrame((time) => this.animate(time));
  }

  buildStudio() {
    const hemisphere = new THREE.HemisphereLight('#f8ffff', '#8bb8ae', 2.65);
    this.scene.add(hemisphere);

    const key = new THREE.DirectionalLight('#fffaf1', 4.1);
    key.position.set(-4.5, 7.5, 8);
    key.castShadow = true;
    key.shadow.mapSize.set(2048, 2048);
    key.shadow.camera.left = -8;
    key.shadow.camera.right = 8;
    key.shadow.camera.top = 6;
    key.shadow.camera.bottom = -6;
    key.shadow.bias = -0.0002;
    this.scene.add(key);

    const fill = new THREE.DirectionalLight('#bceff0', 2.1);
    fill.position.set(6, 1.5, 5);
    this.scene.add(fill);

    const rim = new THREE.SpotLight('#8ee9d5', 22, 24, Math.PI * 0.24, 0.75, 1.2);
    rim.position.set(1, 5, -5);
    rim.target.position.set(0, 0, 0);
    this.scene.add(rim, rim.target);

    const platform = new THREE.Mesh(
      new THREE.CylinderGeometry(5.2, 5.35, 0.2, 96),
      new THREE.MeshStandardMaterial({ color: '#d9efec', roughness: 0.72, metalness: 0.03 }),
    );
    platform.position.y = -1.9;
    platform.receiveShadow = true;
    this.scene.add(platform);

    const ring = new THREE.Mesh(
      new THREE.TorusGeometry(4.85, 0.025, 12, 160),
      new THREE.MeshBasicMaterial({ color: '#5fc6bd', transparent: true, opacity: 0.62 }),
    );
    ring.rotation.x = Math.PI / 2;
    ring.position.y = -1.785;
    this.scene.add(ring);
  }

  createScaleTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 512;
    const context = canvas.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, '#303936');
    gradient.addColorStop(0.38, '#5f6862');
    gradient.addColorStop(0.72, '#959a92');
    gradient.addColorStop(1, '#c8c4b8');
    context.fillStyle = gradient;
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.globalAlpha = 0.24;
    ['#121a19', '#1a2523', '#24302d', '#303b37', '#1a2422'].forEach((color, index) => {
      context.fillStyle = color;
      const x = 105 + index * 158;
      context.fillRect(x, 0, 42, canvas.height);
    });

    context.globalAlpha = 0.38;
    context.strokeStyle = '#263735';
    context.lineWidth = 2.3;
    const scaleWidth = 36;
    const scaleHeight = 25;
    for (let row = -1; row < 22; row += 1) {
      for (let column = -1; column < 31; column += 1) {
        const x = column * scaleWidth + (row % 2 ? scaleWidth / 2 : 0);
        const y = row * scaleHeight;
        context.beginPath();
        context.arc(x, y, scaleWidth * 0.56, 0.17 * Math.PI, 0.83 * Math.PI);
        context.stroke();
      }
    }

    context.globalAlpha = 0.18;
    context.fillStyle = '#f5efe0';
    context.fillRect(0, canvas.height * 0.76, canvas.width, canvas.height * 0.24);
    context.globalAlpha = 1;

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.ClampToEdgeWrapping;
    texture.anisotropy = this.renderer.capabilities.getMaxAnisotropy();
    return texture;
  }

  enhanceMaterials() {
    const scaleTexture = this.createScaleTexture();
    this.model.bodyMaterial.map = scaleTexture;
    this.model.bodyMaterial.color.set('#89948c');
    this.model.bodyMaterial.roughness = 0.54;
    this.model.bodyMaterial.metalness = 0.01;
    this.model.bodyMaterial.needsUpdate = true;

    this.model.root.scale.set(0.94, 0.94, 0.94);
    this.model.root.position.y = 0.04;
    this.model.root.traverse((child) => {
      if (!child.isMesh || !child.material) return;
      child.material.envMapIntensity = 0.8;
      if (child.material.transparent) child.material.alphaToCoverage = true;
    });
  }

  createSwimMotion() {
    const root = this.model.root;
    const tail = this.model.parts.get('caudalFin')?.object;
    const pectoral = this.model.parts.get('pectoralFin')?.object;
    this.swimBase = {
      root: root.position.clone(),
      tailY: tail?.rotation.y ?? 0,
      pectoralZ: pectoral?.rotation.z ?? 0,
    };
    this.swimTimeline = gsap.timeline({ paused: true, repeat: -1, yoyo: true });
    this.swimTimeline.to(root.position, {
      x: this.swimBase.root.x + 0.16,
      y: this.swimBase.root.y + 0.09,
      duration: 1.7,
      ease: 'sine.inOut',
    }, 0);
    if (tail) {
      this.swimTimeline.to(tail.rotation, {
        y: this.swimBase.tailY + 0.18,
        duration: 0.28,
        repeat: 5,
        yoyo: true,
        ease: 'sine.inOut',
      }, 0);
    }
    if (pectoral) {
      this.swimTimeline.to(pectoral.rotation, {
        z: this.swimBase.pectoralZ + 0.15,
        duration: 0.52,
        repeat: 2,
        yoyo: true,
        ease: 'sine.inOut',
      }, 0);
    }
  }

  moveCamera(position, target = this.controls.target, duration = 0.62) {
    gsap.killTweensOf([this.camera.position, this.controls.target]);
    if (this.reduceMotion) {
      this.camera.position.copy(position);
      this.controls.target.copy(target);
      this.controls.update();
      return;
    }
    gsap.to(this.camera.position, {
      x: position.x,
      y: position.y,
      z: position.z,
      duration,
      ease: 'power2.inOut',
      overwrite: true,
    });
    gsap.to(this.controls.target, {
      x: target.x,
      y: target.y,
      z: target.z,
      duration,
      ease: 'power2.inOut',
      overwrite: true,
      onUpdate: () => this.controls.update(),
    });
  }

  focusStep(step) {
    if (this.userAdjustedCamera) return;
    const focusId = step === 2 ? 'gills' : step === 3 ? 'vent' : null;
    const focus = focusId ? this.model.getPartPosition(focusId).multiplyScalar(0.18) : new THREE.Vector3();
    const distance = step === 2 || step === 3 ? this.defaultCameraZ * 0.88 : this.defaultCameraZ;
    this.moveCamera(new THREE.Vector3(0.1, 0.15, distance), focus);
  }

  bindInteractions() {
    const canvas = this.renderer.domElement;
    canvas.addEventListener('pointerdown', (event) => {
      this.pointerDown = { x: event.clientX, y: event.clientY };
    });
    canvas.addEventListener('pointerup', (event) => {
      if (!this.pointerDown) return;
      const moved = Math.hypot(event.clientX - this.pointerDown.x, event.clientY - this.pointerDown.y);
      this.pointerDown = null;
      if (moved <= 6) this.pick(event);
    });
    canvas.addEventListener('pointermove', (event) => this.updateCursor(event));
    canvas.addEventListener('pointerleave', () => {
      canvas.style.cursor = 'grab';
    });

    this.container.addEventListener('keydown', (event) => {
      if (event.key.toLowerCase() === 'f') {
        event.preventDefault();
        this.toggleFullscreen();
        return;
      }
      if (!['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown'].includes(event.key)) return;
      event.preventDefault();
      const direction = ['ArrowDown', 'PageDown'].includes(event.key) ? 1 : -1;
      this.setExplode((this.depth + direction * 10) / 100);
    });
  }

  pointerFromEvent(event) {
    const bounds = this.renderer.domElement.getBoundingClientRect();
    this.pointer.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
    this.pointer.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
    return this.pointer;
  }

  intersectionAt(event) {
    this.raycaster.setFromCamera(this.pointerFromEvent(event), this.camera);
    return this.raycaster.intersectObjects(this.model.selectable, true)
      .find((hit) => this.model.getPartId(hit.object));
  }

  updateCursor(event) {
    if (event.buttons) return;
    this.renderer.domElement.style.cursor = this.intersectionAt(event) ? 'pointer' : 'grab';
  }

  pick(event) {
    const intersection = this.intersectionAt(event);
    if (!intersection) return;
    const id = this.model.getPartId(intersection.object);
    if (id) this.onSelect?.(id);
  }

  layerState() {
    if (this.step === 0) return { name: 'Natural specimen · 3D', key: 'natural' };
    if (this.step < 2) return { name: 'External anatomy · 3D', key: 'external' };
    if (this.step === 2) return { name: 'Gill inspection · 3D', key: 'external' };
    if (this.step === 3) return { name: 'Incision planning · 3D', key: 'external' };
    return { name: 'Internal anatomy · 3D', key: 'internal' };
  }

  setStep(step) {
    this.step = clamp(Number(step), 0, 8);
    this.model.setStep(this.step);
    const defaultDepth = this.step === 7 ? 58 : 0;
    this.depth = defaultDepth;
    this.model.setExplode(defaultDepth / 100);
    this.onDepthChange?.(this.depth, this.layerState());
    this.focusStep(this.step);
  }

  traceIncision() {
    this.model.traceIncision();
  }

  openBodyWall() {
    this.model.openBodyWall();
  }

  setExplode(value) {
    const normalized = clamp(Number(value), 0, 1);
    this.depth = normalized * 100;
    this.model.setExplode(normalized);
    this.onDepthChange?.(this.depth, this.layerState());
  }

  setLabelsVisible(visible) {
    this.model.setLabelsVisible(Boolean(visible));
  }

  setHighlighted(ids) {
    this.model.setHighlighted(ids);
  }

  selectPart(id) {
    this.model.selectPart(id);
    const position = this.model.getPartPosition(id);
    if (position.lengthSq() > 0) {
      const target = position.multiplyScalar(0.18);
      this.moveCamera(this.camera.position.clone(), target, 0.48);
    }
  }

  resetView() {
    this.stopPlayback();
    this.swimTimeline.pause(0);
    this.userAdjustedCamera = false;
    this.model.root.position.copy(this.swimBase.root);
    const tail = this.model.parts.get('caudalFin')?.object;
    const pectoral = this.model.parts.get('pectoralFin')?.object;
    if (tail) tail.rotation.y = this.swimBase.tailY;
    if (pectoral) pectoral.rotation.z = this.swimBase.pectoralZ;
    this.moveCamera(new THREE.Vector3(0.1, 0.15, this.defaultCameraZ), new THREE.Vector3());
    this.setExplode(this.step === 7 ? 0.58 : 0);
  }

  togglePlayback() {
    if (this.reduceMotion) return false;
    this.playing = !this.playing;
    this.controls.autoRotate = false;
    if (this.playing) this.swimTimeline.resume();
    else this.swimTimeline.pause();
    return this.playing;
  }

  stopPlayback() {
    this.playing = false;
    this.controls.autoRotate = false;
    this.swimTimeline.pause();
  }

  async toggleFullscreen() {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
      return false;
    }
    await this.container.parentElement.requestFullscreen();
    return true;
  }

  resize() {
    const width = Math.max(1, this.container.clientWidth);
    const height = Math.max(1, this.container.clientHeight);
    this.camera.aspect = width / height;
    this.defaultCameraZ = Math.max(13.2, 4.35 / (Math.tan(THREE.MathUtils.degToRad(this.camera.fov / 2)) * this.camera.aspect));
    if (!this.userAdjustedCamera) this.camera.position.z = this.defaultCameraZ;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height, false);
    this.labelRenderer.setSize(width, height);
    this.render();
  }

  advanceTime(milliseconds) {
    const delta = clamp(Number(milliseconds) / 1000, 0, 0.25);
    this.elapsed += delta;
    this.model.update(delta, this.elapsed);
    this.controls.update();
    this.render();
  }

  animate(time) {
    const delta = clamp((time - this.lastFrame) / 1000, 0, 0.05);
    this.lastFrame = time;
    this.elapsed += delta;
    this.model.update(delta, this.elapsed);
    this.controls.update();
    this.render();
    this.animationFrame = requestAnimationFrame((nextTime) => this.animate(nextTime));
  }

  render() {
    this.renderer.render(this.scene, this.camera);
    this.labelRenderer.render(this.scene, this.camera);
  }

  stateSnapshot() {
    return {
      coordinateSystem: 'model center is origin; +x points toward the tail, +y points toward the dorsal side, and +z points toward the viewer',
      step: this.step,
      layer: this.layerState().name,
      explodePercent: Math.round(this.depth),
      selectedPart: this.model.selectedId,
      highlightedParts: [...this.model.highlighted],
      labelsVisible: this.model.labelsAll,
      swimming: this.playing,
      autoRotating: false,
      visibleParts: [...this.model.parts.entries()].filter(([, entry]) => entry.object.visible).map(([id]) => id),
    };
  }

  destroy() {
    cancelAnimationFrame(this.animationFrame);
    this.swimTimeline.kill();
    gsap.killTweensOf([this.camera.position, this.controls.target, this.model.root.position]);
    this.resizeObserver.disconnect();
    this.controls.dispose();
    this.renderer.dispose();
    this.renderer.domElement.remove();
    this.labelRenderer.domElement.remove();
  }
}
