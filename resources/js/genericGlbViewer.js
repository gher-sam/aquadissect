import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { gsap } from 'gsap';

export class GenericGlbViewer {
  constructor(container, anatomy, manifest, { onDepthChange } = {}) {
    this.container = container;
    this.externalIds = Object.values(anatomy).filter((item) => item.layer === 'external').map((item) => item.id);
    this.internalIds = Object.values(anatomy).filter((item) => item.layer === 'internal').map((item) => item.id);
    this.playing = false;
    this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.mixer = null;
    this.actions = [];
    this.floatTimeline = null;
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color('#eaf4ff');
    this.camera = new THREE.PerspectiveCamera(35, 1, 0.01, 1000);
    this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    this.renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.domElement.setAttribute('role', 'img');
    this.renderer.domElement.setAttribute('aria-label', `Interactive 3D model of ${manifest.species.commonName}`);
    container.appendChild(this.renderer.domElement);
    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.enablePan = false;
    this.scene.add(new THREE.HemisphereLight('#ffffff', '#607ea7', 3));
    const key = new THREE.DirectionalLight('#ffffff', 4);
    key.position.set(5, 8, 7);
    this.scene.add(key);
    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(container);
    this.ready = new Promise((resolve, reject) => {
      new GLTFLoader().load(manifest.assetRecords.model.url, (gltf) => {
        this.model = gltf.scene;
        this.scene.add(this.model);
        this.fitModel();
        this.setupPlayback(gltf.animations || []);
        onDepthChange?.(0, { name: '3D model', key: 'external' });
        resolve();
      }, undefined, reject);
    });
    this.lastTime = performance.now();
    this.animationFrame = requestAnimationFrame((time) => this.animate(time));
  }

  fitModel() {
    const box = new THREE.Box3().setFromObject(this.model);
    const sphere = box.getBoundingSphere(new THREE.Sphere());
    this.modelRadius = Math.max(sphere.radius, 0.01);
    this.controls.target.copy(sphere.center);
    this.camera.near = Math.max(0.01, sphere.radius / 100);
    this.camera.far = Math.max(100, sphere.radius * 100);
    this.camera.position.set(sphere.center.x, sphere.center.y, sphere.center.z + sphere.radius * 3.2);
    this.camera.updateProjectionMatrix();
    this.resize();
  }

  setupPlayback(animations) {
    if (animations.length) {
      this.mixer = new THREE.AnimationMixer(this.model);
      this.actions = animations.map((clip) => this.mixer.clipAction(clip));
      this.actions.forEach((action) => action.play());
      this.mixer.timeScale = 0;
      return;
    }

    const base = this.model.position.clone();
    const drift = this.modelRadius * 0.045;
    this.floatTimeline = gsap.timeline({ paused: true, repeat: -1, yoyo: true }).to(this.model.position, {
      x: base.x + drift,
      y: base.y + drift * 0.65,
      duration: 1.8,
      ease: 'sine.inOut',
    });
  }

  resize() {
    const width = Math.max(1, this.container.clientWidth);
    const height = Math.max(1, this.container.clientHeight);
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height, false);
  }

  animate(time) {
    const delta = Math.min(0.05, (time - this.lastTime) / 1000);
    this.lastTime = time;
    if (this.playing && this.mixer) this.mixer.update(delta);
    this.controls.update();
    this.renderer.render(this.scene, this.camera);
    this.animationFrame = requestAnimationFrame((next) => this.animate(next));
  }

  setStep() {}
  setHighlighted() {}
  setLabelsVisible() {}
  selectPart() {}
  traceIncision() {}
  openBodyWall() {}
  setExplode() {}
  resetView() { if (this.model) this.fitModel(); }
  togglePlayback() {
    if (this.reduceMotion || !this.model) return false;
    this.playing = !this.playing;
    if (this.mixer) this.mixer.timeScale = this.playing ? 1 : 0;
    else if (this.playing) this.floatTimeline?.resume();
    else this.floatTimeline?.pause();
    return this.playing;
  }
  stopPlayback() {
    this.playing = false;
    if (this.mixer) this.mixer.timeScale = 0;
    this.floatTimeline?.pause();
  }
  async toggleFullscreen() {
    if (document.fullscreenElement) { await document.exitFullscreen(); return false; }
    await this.container.parentElement.requestFullscreen();
    return true;
  }
  destroy() {
    cancelAnimationFrame(this.animationFrame);
    this.stopPlayback();
    this.floatTimeline?.kill();
    if (this.model) gsap.killTweensOf(this.model.position);
    this.resizeObserver.disconnect();
    this.controls.dispose();
    this.renderer.dispose();
    this.renderer.domElement.remove();
  }
}
