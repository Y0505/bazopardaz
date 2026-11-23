/*
 * Product gallery + 3D viewer for the Noctorn demo page.
 * Own code, no libraries: a small WebGL2 glTF (GLB) renderer with orbit controls.
 * The model is only downloaded when the visitor opens the 3D view.
 */
(function () {
  'use strict';

  var stage = document.getElementById('productStage');
  if (!stage) return;

  var img = document.getElementById('stageImage');
  var viewerBox = document.getElementById('viewer');
  var canvas = document.getElementById('viewerCanvas');
  var loading = document.getElementById('viewerLoading');
  var percent = document.getElementById('viewerPercent');
  var bar = document.getElementById('viewerBar');
  var errorBox = document.getElementById('viewerError');
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.gallery-thumbs [role="tab"]'));
  var MODEL_URL = stage.getAttribute('data-model');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------------- feature check */
  function webgl2Supported() {
    try {
      var c = document.createElement('canvas');
      return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
    } catch (e) { return false; }
  }
  var has3D = !!MODEL_URL && webgl2Supported();
  tabs.forEach(function (t) {
    if (t.getAttribute('data-view') === '3d' && !has3D) t.parentNode.removeChild(t);
  });
  tabs = tabs.filter(function (t) { return t.parentNode; });

  /* ---------------------------------------------------------------- gallery tabs */
  var viewer = null;   // created lazily
  var current = tabs.length ? tabs[0].getAttribute('data-view') : 'photo';

  function select(tab, focus) {
    var view = tab.getAttribute('data-view');
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
    });
    if (focus) tab.focus();
    current = view;
    errorBox.hidden = true;
    if (view === '3d') {
      img.hidden = true;
      viewerBox.hidden = false;
      stage.classList.add('is-3d');
      openViewer();
    } else {
      viewerBox.hidden = true;
      img.hidden = false;
      stage.classList.remove('is-3d');
      img.src = tab.getAttribute('data-src');
      img.alt = tab.getAttribute('data-alt') || '';
      if (viewer) viewer.pause();
    }
  }

  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { select(t, false); });
    t.addEventListener('keydown', function (e) {
      var dir = e.key === 'ArrowLeft' ? 1 : e.key === 'ArrowRight' ? -1 : 0;   // RTL: left = next
      if (!dir) return;
      e.preventDefault();
      select(tabs[(i + dir + tabs.length) % tabs.length], true);
    });
  });

  /* ---------------------------------------------------------------- viewer life cycle */
  var failed = false, starting = false;

  function showError(msg) {
    loading.hidden = true;
    errorBox.textContent = msg;
    errorBox.hidden = false;
  }

  function openViewer() {
    if (viewer) { viewer.resume(); return; }
    if (starting) return;
    if (failed) { showError('نمایش سه‌بعدی روی این دستگاه ممکن نشد. عکس‌ها را ببینید.'); return; }
    starting = true;
    loading.hidden = false;
    bar.style.transform = 'scaleX(0.04)';
    percent.textContent = 'در حال بارگذاری مدل سه‌بعدی…';
    var v;
    try { v = createViewer(canvas); } catch (e) { failed = true; starting = false; showError('نمایش سه‌بعدی روی این دستگاه ممکن نشد.'); return; }
    loadGLB(MODEL_URL, function (p) {
      if (p >= 0) {
        bar.style.transform = 'scaleX(' + Math.max(0.04, p).toFixed(3) + ')';
        percent.textContent = 'در حال بارگذاری مدل سه‌بعدی… ' + toFa(Math.round(p * 100)) + '٪';
      }
    }).then(function (glb) {
      percent.textContent = 'در حال آماده‌سازی…';
      return v.setModel(glb);
    }).then(function () {
      viewer = v; starting = false;
      loading.hidden = true;
      stage.classList.add('is-ready');
      if (current === '3d') v.resume(); else v.pause();
    }).catch(function (e) {
      failed = true; starting = false;
      if (window.console) console.error('3D viewer:', e);
      showError('بارگذاری مدل سه‌بعدی انجام نشد. اتصال را بررسی کنید و دوباره امتحان کنید.');
    });
  }

  function toFa(n) {
    return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(d); });
  }

  var open3d = document.getElementById('open3d');
  if (open3d) {
    if (!has3D) open3d.parentNode.removeChild(open3d);
    else open3d.addEventListener('click', function () {
      var t = tabs.filter(function (x) { return x.getAttribute('data-view') === '3d'; })[0];
      if (t) select(t, true);
    });
  }

  /* tool buttons */
  var tools = document.getElementById('viewerTools');
  if (tools) tools.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-act]');
    if (!b || !viewer) return;
    var a = b.getAttribute('data-act');
    if (a === 'in') viewer.zoomBy(0.8);
    else if (a === 'out') viewer.zoomBy(1.25);
    else viewer.reset();
  });

  /* ================================================================= GLB loading */
  function loadGLB(url, onProgress) {
    return fetch(url).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var total = parseInt(res.headers.get('content-length') || '0', 10);
      if (!res.body || !res.body.getReader) {
        onProgress(-1);
        return res.arrayBuffer();
      }
      var reader = res.body.getReader(), parts = [], got = 0;
      return (function pump() {
        return reader.read().then(function (r) {
          if (r.done) {
            var out = new Uint8Array(got), o = 0;
            parts.forEach(function (p) { out.set(p, o); o += p.length; });
            return out.buffer;
          }
          parts.push(r.value); got += r.value.length;
          onProgress(total ? Math.min(0.98, got / total) : -1);
          return pump();
        });
      })();
    }).then(parseGLB);
  }

  function parseGLB(buf) {
    var dv = new DataView(buf);
    if (dv.getUint32(0, true) !== 0x46546C67) throw new Error('not a GLB file');
    var off = 12, json = null, bin = null;
    while (off < buf.byteLength) {
      var len = dv.getUint32(off, true), type = dv.getUint32(off + 4, true);
      var chunk = buf.slice(off + 8, off + 8 + len);
      if (type === 0x4E4F534A) json = JSON.parse(new TextDecoder().decode(chunk));
      else if (type === 0x004E4942) bin = chunk;
      off += 8 + len;
    }
    if (!json || !bin) throw new Error('incomplete GLB');
    return { json: json, bin: bin };
  }

  var COMP = { 5120: Int8Array, 5121: Uint8Array, 5122: Int16Array, 5123: Uint16Array, 5125: Uint32Array, 5126: Float32Array };
  var NUM = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4 };

  /** Raw typed array + layout of one accessor (data stays in its stored format; normalised ints go to the GPU as-is). */
  function readAccessor(glb, index) {
    var a = glb.json.accessors[index], v = glb.json.bufferViews[a.bufferView];
    var T = COMP[a.componentType], n = NUM[a.type];
    var start = (v.byteOffset || 0) + (a.byteOffset || 0);
    var stride = v.byteStride || n * T.BYTES_PER_ELEMENT;
    var bytes = new Uint8Array(glb.bin, start, (a.count - 1) * stride + n * T.BYTES_PER_ELEMENT);
    return { bytes: bytes, count: a.count, size: n, type: a.componentType, normalized: !!a.normalized, stride: stride, min: a.min, max: a.max };
  }

  function decodeImage(glb, index) {
    var info = glb.json.images[index], v = glb.json.bufferViews[info.bufferView];
    var blob = new Blob([new Uint8Array(glb.bin, v.byteOffset || 0, v.byteLength)], { type: info.mimeType || 'image/jpeg' });
    if (window.createImageBitmap) {
      return createImageBitmap(blob, { premultiplyAlpha: 'none', colorSpaceConversion: 'none' });
    }
    return new Promise(function (res, rej) {
      var im = new Image(), u = URL.createObjectURL(blob);
      im.onload = function () { URL.revokeObjectURL(u); res(im); };
      im.onerror = rej; im.src = u;
    });
  }

  /* ================================================================= tiny matrix helpers (column-major) */
  function mul(a, b) {
    var o = new Float32Array(16);
    for (var c = 0; c < 4; c++) for (var r = 0; r < 4; r++) {
      var s = 0; for (var k = 0; k < 4; k++) s += a[k * 4 + r] * b[c * 4 + k];
      o[c * 4 + r] = s;
    }
    return o;
  }
  function perspective(fovy, aspect, n, f) {
    var t = 1 / Math.tan(fovy / 2), o = new Float32Array(16);
    o[0] = t / aspect; o[5] = t; o[10] = (f + n) / (n - f); o[11] = -1; o[14] = 2 * f * n / (n - f);
    return o;
  }
  function rotX(a) { var c = Math.cos(a), s = Math.sin(a); return new Float32Array([1, 0, 0, 0, 0, c, s, 0, 0, -s, c, 0, 0, 0, 0, 1]); }
  function rotY(a) { var c = Math.cos(a), s = Math.sin(a); return new Float32Array([c, 0, -s, 0, 0, 1, 0, 0, s, 0, c, 0, 0, 0, 0, 1]); }
  function scaleTrans(s, tx, ty, tz) { return new Float32Array([s, 0, 0, 0, 0, s, 0, 0, 0, 0, s, 0, s * tx, s * ty, s * tz, 1]); }
  function translateZ(z) { var o = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, z, 1]); return o; }

  /* ================================================================= shaders */
  var VS = '#version 300 es\n' +
    'layout(location=0) in vec3 aPos; layout(location=1) in vec3 aNormal; layout(location=2) in vec2 aUV;\n' +
    'uniform mat4 uModel; uniform mat3 uRot; uniform mat4 uViewProj;\n' +
    'out vec3 vPos; out vec3 vNormal; out vec2 vUV;\n' +
    'void main(){ vec4 w = uModel * vec4(aPos,1.0); vPos = w.xyz; vNormal = uRot * aNormal; vUV = aUV; gl_Position = uViewProj * w; }';

  var FS = '#version 300 es\nprecision highp float;\n' +
    'in vec3 vPos; in vec3 vNormal; in vec2 vUV; out vec4 outColor;\n' +
    'uniform sampler2D uBase; uniform sampler2D uMR; uniform sampler2D uNormalMap; uniform vec3 uCam;\n' +
    'const float PI = 3.14159265;\n' +
    // procedural studio environment in the page's violet palette: soft sky gradient + three soft boxes
    'vec3 env(vec3 d, float rough){\n' +
    '  float h = d.y*0.5+0.5;\n' +
    '  vec3 sky = mix(vec3(0.09,0.085,0.14), vec3(0.46,0.45,0.62), smoothstep(0.0,1.0,h));\n' +
    '  float k = mix(70.0, 5.0, rough);\n' +
    '  float b1 = pow(max(dot(d, normalize(vec3(0.55,0.75,0.55))),0.0), k);\n' +
    '  float b2 = pow(max(dot(d, normalize(vec3(-0.85,0.30,0.45))),0.0), k*0.7);\n' +
    '  float b3 = pow(max(dot(d, normalize(vec3(0.10,0.45,-0.90))),0.0), k*0.8);\n' +
    '  return sky*mix(1.0,0.7,rough) + vec3(1.0,0.96,0.90)*b1*2.4 + vec3(0.62,0.72,1.0)*b2*1.1 + vec3(0.88,0.82,1.0)*b3*1.5;\n' +
    '}\n' +
    'float ggx(float nh, float a){ float a2=a*a; float d = nh*nh*(a2-1.0)+1.0; return a2/(PI*d*d); }\n' +
    'float smithG(float nv, float nl, float a){ float k=(a+1.0)*(a+1.0)/8.0; return (nv/(nv*(1.0-k)+k))*(nl/(nl*(1.0-k)+k)); }\n' +
    'vec3 light(vec3 N, vec3 V, vec3 L, vec3 radiance, vec3 f0, vec3 diff, float a){\n' +
    '  vec3 H = normalize(V+L); float nl=max(dot(N,L),0.0), nv=max(dot(N,V),0.001), nh=max(dot(N,H),0.0), vh=max(dot(V,H),0.0);\n' +
    '  vec3 F = f0 + (1.0-f0)*pow(1.0-vh,5.0);\n' +
    '  vec3 spec = ggx(nh,a)*smithG(nv,nl,a)*F/(4.0*nv*max(nl,0.001));\n' +
    '  return (diff/PI*(1.0-F) + spec) * radiance * nl;\n' +
    '}\n' +
    'void main(){\n' +
    '  vec3 Ng = normalize(vNormal); if(!gl_FrontFacing) Ng = -Ng;\n' +
    '  vec3 V = normalize(uCam - vPos);\n' +
    // normal mapping with a tangent frame derived from screen-space derivatives (the mesh has no tangents)
    '  vec3 dp1 = dFdx(vPos), dp2 = dFdy(vPos); vec2 du1 = dFdx(vUV), du2 = dFdy(vUV);\n' +
    '  vec3 p2 = cross(dp2, Ng), p1 = cross(Ng, dp1);\n' +
    '  vec3 T = p2*du1.x + p1*du2.x; vec3 B = -(p2*du1.y + p1*du2.y);\n' +
    '  float inv = inversesqrt(max(max(dot(T,T),dot(B,B)),1e-12));\n' +
    '  vec3 tn = texture(uNormalMap, vUV).xyz*2.0-1.0;\n' +
    '  vec3 N = normalize(mat3(T*inv, B*inv, Ng) * tn);\n' +
    '  vec3 base = texture(uBase, vUV).rgb;\n' +
    '  vec2 mr = texture(uMR, vUV).gb; float rough = clamp(mr.x, 0.06, 1.0); float metal = mr.y; float a = rough*rough;\n' +
    '  vec3 f0 = mix(vec3(0.04), base, metal); vec3 diff = base*(1.0-metal);\n' +
    '  float nv = max(dot(N,V),0.0);\n' +
    '  vec3 col = vec3(0.0);\n' +
    '  col += light(N,V,normalize(vec3(0.55,0.75,0.55)), vec3(3.7,3.5,3.2), f0, diff, a);\n' +
    '  col += light(N,V,normalize(vec3(-0.85,0.25,0.45)), vec3(0.75,0.90,1.35), f0, diff, a);\n' +
    '  col += light(N,V,normalize(vec3(0.10,0.45,-0.90)), vec3(1.30,1.15,1.70), f0, diff, a);\n' +
    '  float up = N.y*0.5+0.5;\n' +
    '  vec3 irr = mix(vec3(0.045,0.04,0.065), vec3(0.30,0.29,0.40), up);\n' +
    '  vec3 Fr = f0 + (max(vec3(1.0-rough),f0)-f0)*pow(1.0-nv,5.0);\n' +
    '  vec3 R = reflect(-V, N);\n' +
    '  float ao = mix(0.62, 1.0, smoothstep(-0.6, 0.6, N.y));\n' +
    '  col += (diff*irr*(1.0-Fr) + env(R, rough)*Fr*0.85) * ao;\n' +
    '  col = vec3(1.0) - exp(-col*1.25);\n' +
    '  col = pow(col, vec3(1.0/2.2));\n' +
    '  outColor = vec4(col, 1.0);\n' +
    '}';

  var VS_SHADOW = '#version 300 es\nlayout(location=0) in vec2 aP; uniform mat4 uViewProj; uniform float uY; out vec2 vP;\n' +
    'void main(){ vP = aP; gl_Position = uViewProj * vec4(aP.x*1.9, uY, aP.y*1.9, 1.0); }';
  var FS_SHADOW = '#version 300 es\nprecision highp float;\nin vec2 vP; uniform float uYaw; out vec4 outColor;\n' +
    'void main(){ float c=cos(uYaw), s=sin(uYaw); vec2 q = vec2(c*vP.x - s*vP.y, s*vP.x + c*vP.y)*1.9;\n' +
    '  float r = length(vec2(q.x/1.05, q.y/0.38)); float a = 0.62*smoothstep(1.0, 0.0, r); a *= a*0.5 + 0.5*smoothstep(1.0,0.0,r);\n' +
    '  outColor = vec4(0.0, 0.0, 0.0, a); }';

  function compile(gl, type, src) {
    var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error('shader: ' + gl.getShaderInfoLog(s));
    return s;
  }
  function program(gl, vs, fs) {
    var p = gl.createProgram();
    gl.attachShader(p, compile(gl, gl.VERTEX_SHADER, vs)); gl.attachShader(p, compile(gl, gl.FRAGMENT_SHADER, fs));
    gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error('link: ' + gl.getProgramInfoLog(p));
    return p;
  }

  /* ================================================================= viewer */
  function createViewer(cv) {
    var gl = cv.getContext('webgl2', { antialias: true, alpha: true, premultipliedAlpha: true, powerPreference: 'default' });
    if (!gl) throw new Error('no webgl2');
    var prog = program(gl, VS, FS), shadowProg = program(gl, VS_SHADOW, FS_SHADOW);
    var U = {}, S = {};
    ['uModel', 'uRot', 'uViewProj', 'uBase', 'uMR', 'uNormalMap', 'uCam'].forEach(function (n) { U[n] = gl.getUniformLocation(prog, n); });
    ['uViewProj', 'uY', 'uYaw'].forEach(function (n) { S[n] = gl.getUniformLocation(shadowProg, n); });

    var quad = gl.createVertexArray(), quadBuf = gl.createBuffer();
    gl.bindVertexArray(quad); gl.bindBuffer(gl.ARRAY_BUFFER, quadBuf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

    var vao = null, indexCount = 0, textures = [], norm = { s: 1, c: [0, 0, 0], bottom: -0.5 };
    var FOV = 26 * Math.PI / 180, DEFAULT_YAW = -0.7, DEFAULT_PITCH = 0.16;
    var st = { yaw: DEFAULT_YAW, pitch: DEFAULT_PITCH, zoom: 1, vyaw: 0, vpitch: 0, last: 0, idleSince: 0, drag: false, auto: !reduceMotion };
    var running = false, raf = 0, w = 1, h = 1, disposed = false;

    function upload(att, loc) {
      var buf = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.bufferData(gl.ARRAY_BUFFER, att.bytes, gl.STATIC_DRAW);
      gl.enableVertexAttribArray(loc);
      gl.vertexAttribPointer(loc, att.size, att.type, att.normalized, att.stride, 0);
    }

    function makeTexture(bitmap, srgb) {
      var t = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, t);
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
      gl.pixelStorei(gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, false);
      gl.texImage2D(gl.TEXTURE_2D, 0, srgb ? gl.SRGB8_ALPHA8 : gl.RGBA8, gl.RGBA, gl.UNSIGNED_BYTE, bitmap);
      gl.generateMipmap(gl.TEXTURE_2D);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.REPEAT);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.REPEAT);
      var ext = gl.getExtension('EXT_texture_filter_anisotropic');
      if (ext) gl.texParameterf(gl.TEXTURE_2D, ext.TEXTURE_MAX_ANISOTROPY_EXT, Math.min(4, gl.getParameter(ext.MAX_TEXTURE_MAX_ANISOTROPY_EXT)));
      return t;
    }

    function setModel(glb) {
      var prim = glb.json.meshes[0].primitives[0];
      var mat = glb.json.materials[prim.material || 0];
      var pbr = mat.pbrMetallicRoughness || {};
      var pos = readAccessor(glb, prim.attributes.POSITION), nrm = readAccessor(glb, prim.attributes.NORMAL), uv = readAccessor(glb, prim.attributes.TEXCOORD_0);
      var idx = readAccessor(glb, prim.indices);
      var texIdx = function (ref) { return glb.json.textures[ref.index].source; };
      var jobs = [decodeImage(glb, texIdx(pbr.baseColorTexture)), decodeImage(glb, texIdx(pbr.metallicRoughnessTexture)), decodeImage(glb, texIdx(mat.normalTexture))];
      return Promise.all(jobs).then(function (maps) {
        vao = gl.createVertexArray(); gl.bindVertexArray(vao);
        upload(pos, 0); upload(nrm, 1); upload(uv, 2);
        var ib = gl.createBuffer(); gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ib);
        gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, idx.bytes, gl.STATIC_DRAW);
        indexCount = idx.count; indexType = idx.type;
        textures = [makeTexture(maps[0], true), makeTexture(maps[1], false), makeTexture(maps[2], false)];
        maps.forEach(function (m) { if (m.close) m.close(); });
        var mn = pos.min, mx = pos.max;
        var half = Math.max(mx[0] - mn[0], mx[1] - mn[1], mx[2] - mn[2]) / 2;
        norm.s = 1 / half;
        norm.c = [(mn[0] + mx[0]) / 2, (mn[1] + mx[1]) / 2, (mn[2] + mx[2]) / 2];
        norm.bottom = -(mx[1] - mn[1]) / 2 * norm.s - 0.02;
        resize(true);
        st.last = performance.now();
        draw();
      });
    }
    var indexType = 5125;

    function resize(force) {
      var rect = cv.getBoundingClientRect();
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      var nw = Math.max(1, Math.round(rect.width * dpr)), nh = Math.max(1, Math.round(rect.height * dpr));
      if (force || nw !== cv.width || nh !== cv.height) { cv.width = nw; cv.height = nh; }
      w = nw; h = nh; gl.viewport(0, 0, w, h);
    }

    function distance() {
      var aspect = w / h, t = Math.tan(FOV / 2);
      return 1.1 * Math.max(1.0 / (t * aspect), 0.62 / t) * st.zoom + 0.3;
    }

    function draw() {
      if (!vao) return;
      var dist = distance();
      var proj = perspective(FOV, w / h, 0.1, dist + 6), view = translateZ(-dist), vp = mul(proj, view);
      var R = mul(rotX(st.pitch), rotY(st.yaw));
      var model = mul(R, scaleTrans(norm.s, -norm.c[0], -norm.c[1], -norm.c[2]));
      var rot3 = new Float32Array([R[0], R[1], R[2], R[4], R[5], R[6], R[8], R[9], R[10]]);
      gl.clearColor(0, 0, 0, 0);
      gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

      gl.disable(gl.DEPTH_TEST); gl.enable(gl.BLEND); gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
      gl.useProgram(shadowProg); gl.uniformMatrix4fv(S.uViewProj, false, vp);
      gl.uniform1f(S.uY, norm.bottom); gl.uniform1f(S.uYaw, st.yaw);
      gl.bindVertexArray(quad); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

      gl.disable(gl.BLEND); gl.enable(gl.DEPTH_TEST); gl.depthFunc(gl.LEQUAL);
      gl.useProgram(prog);
      gl.uniformMatrix4fv(U.uModel, false, model); gl.uniformMatrix3fv(U.uRot, false, rot3); gl.uniformMatrix4fv(U.uViewProj, false, vp);
      gl.uniform3f(U.uCam, 0, 0, dist);
      for (var i = 0; i < 3; i++) { gl.activeTexture(gl.TEXTURE0 + i); gl.bindTexture(gl.TEXTURE_2D, textures[i]); }
      gl.uniform1i(U.uBase, 0); gl.uniform1i(U.uMR, 1); gl.uniform1i(U.uNormalMap, 2);
      gl.bindVertexArray(vao);
      gl.drawElements(gl.TRIANGLES, indexCount, indexType, 0);
    }

    function frame(now) {
      raf = 0;
      if (!running || disposed) return;
      var dt = Math.min(0.05, (now - st.last) / 1000 || 0.016); st.last = now;
      if (!st.drag) {
        st.yaw += st.vyaw * dt * 60; st.pitch += st.vpitch * dt * 60;
        st.vyaw *= 0.93; st.vpitch *= 0.93;
        if (Math.abs(st.vyaw) < 0.00005) st.vyaw = 0;
        if (Math.abs(st.vpitch) < 0.00005) st.vpitch = 0;
        if (st.auto && st.vyaw === 0 && now - st.idleSince > 2500) st.yaw += 0.32 * dt;
      }
      st.pitch = Math.max(-0.12, Math.min(0.62, st.pitch));
      resize(false);
      draw();
      raf = requestAnimationFrame(frame);
    }

    function resume() {
      if (running || !vao) return;
      running = true; st.last = performance.now(); st.idleSince = st.last;
      if (!raf) raf = requestAnimationFrame(frame);
    }
    function pause() { running = false; if (raf) { cancelAnimationFrame(raf); raf = 0; } }
    function touched() { st.idleSince = performance.now(); }

    /* ---------- input: drag / touch (horizontal only on touch, so the page can still scroll), wheel, keyboard */
    var pid = null, lx = 0, ly = 0, isTouch = false;
    cv.addEventListener('pointerdown', function (e) {
      pid = e.pointerId; lx = e.clientX; ly = e.clientY; isTouch = e.pointerType === 'touch'; st.drag = true; st.vyaw = st.vpitch = 0;
      try { cv.setPointerCapture(pid); } catch (er) { /* ignore */ }
      touched();
    });
    cv.addEventListener('pointermove', function (e) {
      if (!st.drag || e.pointerId !== pid) return;
      var dx = e.clientX - lx, dy = e.clientY - ly; lx = e.clientX; ly = e.clientY;
      st.yaw += dx * 0.0085; st.vyaw = dx * 0.0085 * 0.6;
      if (!isTouch) { st.pitch += dy * 0.006; st.vpitch = dy * 0.006 * 0.5; }
      touched();
    });
    function up(e) { if (e.pointerId === pid) { st.drag = false; pid = null; touched(); } }
    cv.addEventListener('pointerup', up); cv.addEventListener('pointercancel', up);
    cv.addEventListener('wheel', function (e) {
      if (!e.ctrlKey) return;               // plain wheel keeps scrolling the page; Ctrl + wheel / trackpad pinch zooms
      e.preventDefault(); zoomBy(e.deltaY > 0 ? 1.08 : 0.92);
    }, { passive: false });
    cv.addEventListener('dblclick', function () { reset(); });
    cv.addEventListener('keydown', function (e) {
      var k = e.key, used = true;
      if (k === 'ArrowLeft') st.yaw -= 0.12; else if (k === 'ArrowRight') st.yaw += 0.12;
      else if (k === 'ArrowUp') st.pitch -= 0.06; else if (k === 'ArrowDown') st.pitch += 0.06;
      else if (k === '+' || k === '=') zoomBy(0.88); else if (k === '-') zoomBy(1.12);
      else if (k === '0' || k === 'Home') reset(); else used = false;
      if (used) { e.preventDefault(); touched(); }
    });

    function zoomBy(f) { st.zoom = Math.max(0.55, Math.min(1.7, st.zoom * f)); touched(); }
    function reset() { st.yaw = DEFAULT_YAW; st.pitch = DEFAULT_PITCH; st.zoom = 1; st.vyaw = st.vpitch = 0; touched(); }

    /* keep the loop honest: stop when the tab is hidden or the viewer scrolls out of sight */
    document.addEventListener('visibilitychange', function () { if (document.hidden) pause(); else if (current === '3d') resume(); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        if (current !== '3d') return;
        if (en[0].isIntersecting) resume(); else pause();
      }, { threshold: 0.05 }).observe(cv);
    }
    if ('ResizeObserver' in window) new ResizeObserver(function () { if (vao && !running) { resize(true); draw(); } }).observe(cv);
    cv.addEventListener('webglcontextlost', function (e) {
      e.preventDefault(); pause(); disposed = true; failed = true; viewer = null;
      showError('نمایش سه‌بعدی متوقف شد. صفحه را دوباره بارگذاری کنید.');
    });

    return { setModel: setModel, resume: resume, pause: pause, zoomBy: zoomBy, reset: reset };
  }
})();
