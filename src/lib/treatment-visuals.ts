/** Closest existing teaching picture for each treatment. The desk can replace every key. */
export const TREATMENT_VISUALS: Record<string, { src: string; cms: string; fit: string }> = {
  "wisdom-tooth-surgery": {
    src: "/visuals/wisdom.webp",
    cms: "visual.wisdom",
    fit: "object-cover object-[28%_42%]",
  },
  "dental-implants": {
    src: "/visuals/implant.webp",
    cms: "visual.implant",
    fit: "object-cover object-center",
  },
  "orthognathic-surgery": {
    src: "/visuals/orthognathic.webp",
    cms: "visual.ortho",
    fit: "object-cover object-[62%_center]",
  },
  "oral-pathology": {
    src: "/visuals/jaw-studio.webp",
    cms: "visual.pathology",
    fit: "object-cover object-[center_62%]",
  },
  "bone-graft-sinus-lift": {
    src: "/visuals/implant.webp",
    cms: "visual.graft",
    fit: "object-cover object-[center_84%]",
  },
  "all-on-4": {
    src: "/visuals/implant.webp",
    cms: "visual.allon4",
    fit: "object-cover object-[center_16%]",
  },
  "facial-fractures": {
    src: "/visuals/orthognathic.webp",
    cms: "visual.fracture",
    fit: "object-cover object-[38%_28%]",
  },
  tmj: {
    src: "/visuals/jaw-studio.webp",
    cms: "visual.tmj",
    fit: "object-cover object-[center_12%]",
  },
  "cosmetic-jaw": {
    src: "/visuals/orthognathic.webp",
    cms: "visual.cosmetic",
    fit: "object-cover object-[55%_78%]",
  },
};

export function treatmentVisual(slug: string) {
  return (
    TREATMENT_VISUALS[slug] ?? {
      src: "/visuals/jaw-studio.webp",
      cms: "visual.pathology",
      fit: "object-cover object-center",
    }
  );
}
