/**
 * Procedural, stylised mandible — medical-editorial look.
 *
 * Built entirely from Three.js primitives:
 *  - body:   a custom swept super-elliptical cross-section along a U-shaped
 *            spline (variable height / thickness: chin, molar buttress, taper)
 *  - rami:   ExtrudeGeometry from a hand-drawn smooth outline (coronoid
 *            process, sigmoid notch, condyle, gonial angle) with soft bevels
 *  - teeth:  16 crowns via LatheGeometry with per-type post-processing
 *            (incisor blades, pointed canines, cusped premolars/molars)
 *  - nerve:  inferior alveolar nerve tubes for the anatomy scene
 *
 * Geometry is created once and cached at module level so the hero and the
 * anatomy scene share the same buffers.
 *
 * Conventions: +Y up, the chin faces +Z, +X is the model's "right" side.
 */
import {
  BufferGeometry,
  CatmullRomCurve3,
  Color,
  ExtrudeGeometry,
  Float32BufferAttribute,
  LatheGeometry,
  Matrix4,
  Shape,
  TubeGeometry,
  Vector2,
  Vector3,
  type BufferAttribute,
} from "three";
import {
  mergeGeometries,
  mergeVertices,
} from "three/examples/jsm/utils/BufferGeometryUtils.js";

/* ------------------------------------------------------------------ */
/* Helpers                                                              */
/* ------------------------------------------------------------------ */

const clamp = (v: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, v));

const smoothstep = (a: number, b: number, x: number) => {
  const t = clamp((x - a) / (b - a), 0, 1);
  return t * t * (3 - 2 * t);
};

const gauss = (x: number, center: number, sigma: number) =>
  Math.exp(-(((x - center) / sigma) ** 2));

const UP = new Vector3(0, 1, 0);

/** Where the model's visual centre sits — scenes offset by this. */
export const MANDIBLE_CENTER = new Vector3(0, 0.28, -0.14);
/** Y of the lowest point of the model (for contact shadow placement). */
export const MANDIBLE_BOTTOM_Y = -0.3;

/* ------------------------------------------------------------------ */
/* Body (swept profile)                                                 */
/* ------------------------------------------------------------------ */

/** Half arch, centre of chin → end of the body inside the ramus. [x, z] */
const ARCH_HALF: ReadonlyArray<readonly [number, number]> = [
  [0, 1.02],
  [0.45, 0.93],
  [0.82, 0.68],
  [1.08, 0.3],
  [1.2, -0.15],
  [1.22, -0.6],
  [1.24, -1.1],
];

function buildArchCurve(): CatmullRomCurve3 {
  const left = [...ARCH_HALF]
    .slice(1)
    .reverse()
    .map(([x, z]) => new Vector3(-x, 0, z));
  const right = ARCH_HALF.map(([x, z]) => new Vector3(x, 0, z));

  return new CatmullRomCurve3([...left, ...right], false, "centripetal");
}

/** a = 0 at the chin, 1 at the ends of the body. */
const bodyHeight = (a: number) =>
  0.44 +
  0.15 * gauss(a, 0, 0.16) +
  0.07 * smoothstep(0.7, 0.92, a) -
  0.12 * smoothstep(0.94, 1, a);

const bodyWidth = (a: number) => {
  const w = 0.3 + 0.05 * gauss(a, 0, 0.25) + 0.07 * gauss(a, 0.72, 0.14);
  return w * (1 - 0.55 * smoothstep(0.9, 1, a));
};

const bodyCenterY = (a: number) => -0.05 * gauss(a, 0, 0.18);

const bodyTopY = (a: number) => bodyCenterY(a) + bodyHeight(a) / 2;

function frameAt(curve: CatmullRomCurve3, t: number) {
  const point = curve.getPointAt(t);
  const tangent = curve.getTangentAt(t);
  tangent.y = 0;
  tangent.normalize();
  /** e1 × up = tangent  ⇒  e1 points to the lingual (inner) side. */
  const lingual = new Vector3().crossVectors(UP, tangent).normalize();
  const buccal = lingual.clone().negate();

  return { point, tangent, lingual, buccal };
}

function buildBodyGeometry(curve: CatmullRomCurve3): BufferGeometry {
  const rings = 180;
  const around = 32;
  const exponent = 3.1; // super-ellipse: rounded-square cross-section

  const positions: number[] = [];
  const indices: number[] = [];

  for (let i = 0; i <= rings; i += 1) {
    const t = i / rings;
    const a = Math.abs(t - 0.5) * 2;
    const { point, lingual } = frameAt(curve, t);
    const w = bodyWidth(a);
    const h = bodyHeight(a);
    const yc = bodyCenterY(a);

    for (let j = 0; j < around; j += 1) {
      const phi = (j / around) * Math.PI * 2;
      const c = Math.cos(phi);
      const s = Math.sin(phi);
      let cx = Math.sign(c) * Math.abs(c) ** (2 / exponent);
      const cy = Math.sign(s) * Math.abs(s) ** (2 / exponent);

      /** Narrower alveolar ridge on top, fuller lower border. */
      cx *= 1 - 0.14 * Math.max(0, cy);

      const p = new Vector3()
        .copy(point)
        .addScaledVector(lingual, cx * (w / 2))
        .addScaledVector(UP, yc + cy * (h / 2));

      positions.push(p.x, p.y, p.z);
    }
  }

  for (let i = 0; i < rings; i += 1) {
    for (let j = 0; j < around; j += 1) {
      const jn = (j + 1) % around;
      const A = i * around + j;
      const B = i * around + jn;
      const C = (i + 1) * around + j;
      const D = (i + 1) * around + jn;

      indices.push(A, B, C, B, D, C);
    }
  }

  /** End caps (hidden inside the rami, but keep the mesh watertight). */
  const capStart = positions.length / 3;
  const startCentre = curve.getPointAt(0);
  positions.push(startCentre.x, bodyCenterY(1), startCentre.z);
  const capEnd = positions.length / 3;
  const endCentre = curve.getPointAt(1);
  positions.push(endCentre.x, bodyCenterY(1), endCentre.z);

  for (let j = 0; j < around; j += 1) {
    const jn = (j + 1) % around;
    indices.push(capStart, jn, j);
    indices.push(capEnd, rings * around + j, rings * around + jn);
  }

  const geometry = new BufferGeometry();
  geometry.setAttribute("position", new Float32BufferAttribute(positions, 3));
  geometry.setIndex(indices);
  geometry.computeVertexNormals();

  return geometry;
}

/* ------------------------------------------------------------------ */
/* Rami (extruded outlines)                                             */
/* ------------------------------------------------------------------ */

/**
 * Outline in (u, v): u runs backwards from the retromolar region,
 * v runs upwards from the body's centre line. Clockwise from lower-front.
 */
const RAMUS_OUTLINE: ReadonlyArray<readonly [number, number]> = [
  [0.0, -0.27],
  [0.3, -0.31],
  [0.62, -0.33],
  [0.84, -0.27],
  [0.91, -0.1],
  [0.89, 0.2],
  [0.85, 0.55],
  [0.81, 0.9],
  [0.8, 1.12],
  [0.88, 1.24],
  [0.8, 1.36],
  [0.66, 1.37],
  [0.58, 1.26],
  [0.56, 1.14],
  [0.5, 1.04],
  [0.38, 1.12],
  [0.26, 1.3],
  [0.14, 1.43],
  [0.08, 1.38],
  [0.05, 1.2],
  [0.02, 0.85],
  [-0.02, 0.45],
  [-0.04, 0.12],
];

const RAMUS_SCALE_U = 0.9;
const RAMUS_SCALE_V = 0.8;
const RAMUS_Z = -0.5;
const RAMUS_X = 1.235;
const RAMUS_TILT = 0.09;
const RAMUS_DEPTH = 0.13;

function buildRamusShape(): Shape {
  const curve = new CatmullRomCurve3(
    RAMUS_OUTLINE.map(
      ([u, v]) => new Vector3(u * RAMUS_SCALE_U, v * RAMUS_SCALE_V, 0),
    ),
    true,
    "centripetal",
  );
  const points = curve.getPoints(260).map((p) => new Vector2(p.x, p.y));
  points.pop(); // closed curve repeats its first point

  return new Shape(points);
}

function buildRamusGeometry(side: 1 | -1): BufferGeometry {
  const geometry = new ExtrudeGeometry(buildRamusShape(), {
    depth: RAMUS_DEPTH,
    bevelEnabled: true,
    bevelThickness: 0.04,
    bevelSize: 0.035,
    bevelSegments: 5,
    steps: 1,
    curveSegments: 1,
  });

  geometry.translate(0, 0, -RAMUS_DEPTH / 2);

  /** local x → world −z (backwards), local z (thickness) → world x. */
  const matrix = new Matrix4()
    .makeTranslation(side * RAMUS_X, 0, RAMUS_Z)
    .multiply(new Matrix4().makeRotationZ(-side * RAMUS_TILT))
    .multiply(new Matrix4().makeRotationY(Math.PI / 2));

  geometry.applyMatrix4(matrix);

  return geometry;
}

/* ------------------------------------------------------------------ */
/* Teeth                                                                */
/* ------------------------------------------------------------------ */

type ToothKind = "incisor" | "canine" | "premolar" | "molar";

type ToothSpec = {
  id: string;
  kind: ToothKind;
  /** mesio-distal width */
  w: number;
  /** bucco-lingual depth */
  d: number;
  /** crown height */
  h: number;
};

/** From the midline backwards. */
const TOOTH_SPECS: ReadonlyArray<ToothSpec> = [
  { id: "I1", kind: "incisor", w: 0.15, d: 0.13, h: 0.3 },
  { id: "I2", kind: "incisor", w: 0.17, d: 0.14, h: 0.31 },
  { id: "C", kind: "canine", w: 0.2, d: 0.2, h: 0.38 },
  { id: "P1", kind: "premolar", w: 0.21, d: 0.22, h: 0.27 },
  { id: "P2", kind: "premolar", w: 0.21, d: 0.23, h: 0.27 },
  { id: "M1", kind: "molar", w: 0.38, d: 0.3, h: 0.25 },
  { id: "M2", kind: "molar", w: 0.34, d: 0.29, h: 0.24 },
  { id: "M3", kind: "molar", w: 0.3, d: 0.26, h: 0.22 },
];

const TOOTH_GAP = 0.008;

/** (radius, y) — radius 0.5 == full width. Base sits below the gum line. */
const TOOTH_PROFILE: Record<ToothKind, ReadonlyArray<readonly [number, number]>> = {
  incisor: [
    [0, -0.18],
    [0.4, -0.18],
    [0.47, 0],
    [0.5, 0.25],
    [0.46, 0.6],
    [0.38, 0.88],
    [0.26, 0.98],
    [0, 1],
  ],
  canine: [
    [0, -0.18],
    [0.4, -0.18],
    [0.48, 0],
    [0.5, 0.2],
    [0.42, 0.5],
    [0.28, 0.78],
    [0.12, 0.95],
    [0, 1.08],
  ],
  premolar: [
    [0, -0.18],
    [0.42, -0.18],
    [0.49, 0],
    [0.5, 0.3],
    [0.46, 0.6],
    [0.42, 0.78],
    [0.36, 0.88],
    [0.18, 0.82],
    [0, 0.78],
  ],
  molar: [
    [0, -0.18],
    [0.44, -0.18],
    [0.5, 0],
    [0.5, 0.35],
    [0.47, 0.62],
    [0.4, 0.78],
    [0.3, 0.86],
    [0.15, 0.76],
    [0, 0.72],
  ],
};

const SQUARENESS: Record<ToothKind, number> = {
  incisor: 2.4,
  canine: 2,
  premolar: 2.4,
  molar: 3,
};

const ENAMEL_BASE = new Color("#c9bda8");
const ENAMEL_TOP = new Color("#fffdf8");

function buildToothGeometry(spec: ToothSpec): BufferGeometry {
  const profile = TOOTH_PROFILE[spec.kind].map(([r, y]) => new Vector2(r, y));
  let geometry: BufferGeometry = new LatheGeometry(profile, 28);
  const position = geometry.getAttribute("position") as BufferAttribute;
  const n = SQUARENESS[spec.kind];

  for (let i = 0; i < position.count; i += 1) {
    let x = position.getX(i);
    let y = position.getY(i);
    let z = position.getZ(i);
    const r = Math.hypot(x, z);

    if (r > 1e-5) {
      const theta = Math.atan2(z, x);
      const c = Math.abs(Math.cos(theta));
      const s = Math.abs(Math.sin(theta));
      const square = 1 / (c ** n + s ** n) ** (1 / n);

      x *= square;
      z *= square;

      const rim = clamp(r / 0.5, 0, 1);
      const crownZone = smoothstep(0.45, 0.8, y);

      if (spec.kind === "incisor") {
        /** Thin the incisal edge into a blade. */
        z *= 1 - 0.55 * smoothstep(0.45, 1, y);
      } else if (spec.kind === "premolar") {
        y += -Math.cos(2 * theta) * 0.075 * rim * crownZone;
      } else if (spec.kind === "molar") {
        y += -Math.cos(4 * theta) * 0.06 * rim * crownZone;
      }
    }

    position.setXYZ(i, x * spec.w, y * spec.h, z * spec.d);
  }

  geometry.deleteAttribute("uv");
  geometry.deleteAttribute("normal");
  geometry = mergeVertices(geometry, 1e-4);
  geometry.computeVertexNormals();

  /** Gum-line → enamel gradient baked as vertex colour (cheap AO feel). */
  const merged = geometry.getAttribute("position") as BufferAttribute;
  const colors = new Float32Array(merged.count * 3);
  const tmp = new Color();

  for (let i = 0; i < merged.count; i += 1) {
    const yUnit = merged.getY(i) / spec.h;
    tmp.copy(ENAMEL_BASE).lerp(ENAMEL_TOP, smoothstep(-0.02, 0.38, yUnit));
    colors[i * 3] = tmp.r;
    colors[i * 3 + 1] = tmp.g;
    colors[i * 3 + 2] = tmp.b;
  }

  geometry.setAttribute("color", new Float32BufferAttribute(colors, 3));

  return geometry;
}

/* ------------------------------------------------------------------ */
/* Assembly                                                             */
/* ------------------------------------------------------------------ */

export type MandibleAnchor = {
  position: Vector3;
  normal: Vector3;
};

export type MandibleParts = {
  /** Body + both rami (ivory bone). Vertex-coloured. */
  bone: BufferGeometry;
  /** All 16 crowns merged (enamel). Vertex-coloured. */
  teeth: BufferGeometry;
  /** Both inferior alveolar nerve tubes (for the anatomy scene). */
  nerve: BufferGeometry;
  anchors: {
    chin: MandibleAnchor;
    wisdomTooth: MandibleAnchor;
    nerve: MandibleAnchor;
  };
};

function shadeBone(geometry: BufferGeometry) {
  const normal = geometry.getAttribute("normal") as BufferAttribute;
  const position = geometry.getAttribute("position") as BufferAttribute;
  const colors = new Float32Array(normal.count * 3);

  for (let i = 0; i < normal.count; i += 1) {
    const ny = normal.getY(i);
    /** Undersides slightly cooler/darker, upper surfaces luminous. */
    const light = 0.86 + 0.14 * clamp(ny * 0.5 + 0.5, 0, 1);
    /** Faint warm tint toward the alveolar ridge. */
    const warm = smoothstep(0.1, 0.3, position.getY(i)) * 0.02;

    colors[i * 3] = clamp(light + warm, 0, 1);
    colors[i * 3 + 1] = clamp(light, 0, 1);
    colors[i * 3 + 2] = clamp(light - warm * 1.5, 0, 1);
  }

  geometry.setAttribute("color", new Float32BufferAttribute(colors, 3));
}

function buildNerveGeometry(curve: CatmullRomCurve3): BufferGeometry {
  const tubes: BufferGeometry[] = [];

  for (const side of [1, -1] as const) {
    const points: Vector3[] = [];

    /** Mandibular foramen inside the ramus. */
    points.push(new Vector3(side * 1.22, 0.2, RAMUS_Z - 0.4));

    const from = 0.9;
    const to = 0.26;
    const steps = 14;

    for (let i = 0; i <= steps; i += 1) {
      const a = from + ((to - from) * i) / steps;
      const t = 0.5 + (side * a) / 2;
      const { point, buccal } = frameAt(curve, t);
      const y = bodyCenterY(a) - bodyHeight(a) * 0.14;

      points.push(
        new Vector3(point.x, y, point.z).addScaledVector(buccal, bodyWidth(a) * 0.04),
      );
    }

    /** Mental foramen — emerges slightly outward. */
    const a = 0.26;
    const { point, buccal } = frameAt(curve, 0.5 + (side * a) / 2);
    points.push(
      new Vector3(point.x, bodyCenterY(a) - 0.02, point.z).addScaledVector(
        buccal,
        bodyWidth(a) / 2 + 0.03,
      ),
    );

    const path = new CatmullRomCurve3(points, false, "centripetal");
    const tube = new TubeGeometry(path, 90, 0.022, 10, false);
    tube.deleteAttribute("uv");
    tubes.push(tube);
  }

  return mergeGeometries(tubes, false) ?? tubes[0];
}

function build(): MandibleParts {
  const curve = buildArchCurve();
  const length = curve.getLength();

  /* --- bone --- */
  const body = buildBodyGeometry(curve).toNonIndexed();
  body.deleteAttribute("uv");

  const rami = [buildRamusGeometry(1), buildRamusGeometry(-1)];
  rami.forEach((g) => g.deleteAttribute("uv"));

  const bone =
    mergeGeometries([body, ...rami], false) ??
    (() => {
      throw new Error("Failed to merge mandible geometry");
    })();
  shadeBone(bone);

  /* --- teeth --- */
  const crowns: BufferGeometry[] = [];
  let wisdom: MandibleAnchor | null = null;

  for (const side of [1, -1] as const) {
    let distance = TOOTH_GAP / 2;

    for (const spec of TOOTH_SPECS) {
      const centreDistance = distance + spec.w / 2;
      const t = 0.5 + (side * centreDistance) / length;
      const a = Math.abs(t - 0.5) * 2;
      const { point, tangent, buccal } = frameAt(curve, t);

      const y = bodyTopY(a) - 0.035;
      const crown = buildToothGeometry(spec);

      /** Local axes: x = along arch, y = up, z = buccal (outwards). */
      const basis = new Matrix4().makeBasis(
        tangent,
        UP,
        new Vector3().crossVectors(tangent, UP),
      );
      const matrix = new Matrix4()
        .makeTranslation(point.x, y, point.z)
        .multiply(basis);

      /** Wisdom teeth lean slightly mesially, as they often erupt. */
      if (spec.id === "M3") {
        matrix.multiply(new Matrix4().makeRotationZ(side * -0.1));
      }

      crown.applyMatrix4(matrix);
      crowns.push(crown);

      if (spec.id === "M3" && side === 1) {
        wisdom = {
          position: new Vector3(point.x, y + spec.h * 0.55, point.z).addScaledVector(
            buccal,
            spec.d / 2 + 0.03,
          ),
          normal: buccal.clone(),
        };
      }

      distance += spec.w + TOOTH_GAP;
    }
  }

  const teeth =
    mergeGeometries(crowns, false) ??
    (() => {
      throw new Error("Failed to merge teeth geometry");
    })();

  crowns.forEach((g) => g.dispose());

  /* --- anchors --- */
  const chinFrame = frameAt(curve, 0.5);
  const chinNormal = chinFrame.buccal.clone();
  if (chinNormal.z < 0) chinNormal.negate();

  const mentalA = 0.26;
  const mentalFrame = frameAt(curve, 0.5 + mentalA / 2);

  return {
    bone,
    teeth,
    nerve: buildNerveGeometry(curve),
    anchors: {
      chin: {
        position: new Vector3(
          chinFrame.point.x,
          bodyCenterY(0) - 0.08,
          chinFrame.point.z + bodyWidth(0) / 2 + 0.04,
        ),
        normal: chinNormal,
      },
      wisdomTooth:
        wisdom ?? {
          position: new Vector3(1.2, 0.3, -0.3),
          normal: new Vector3(1, 0, 0),
        },
      nerve: {
        position: new Vector3(mentalFrame.point.x, bodyCenterY(mentalA) - 0.02, mentalFrame.point.z).addScaledVector(
          mentalFrame.buccal,
          bodyWidth(mentalA) / 2 + 0.05,
        ),
        normal: mentalFrame.buccal.clone(),
      },
    },
  };
}

let cache: MandibleParts | null = null;

/** Shared, lazily-built geometry. Do not dispose — scenes reuse it. */
export function getMandibleParts(): MandibleParts {
  if (!cache) cache = build();
  return cache;
}
