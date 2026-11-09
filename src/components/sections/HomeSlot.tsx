"use client";

import type { ReactNode } from "react";

import { useAppearance, type HomeSection } from "@/components/providers/Appearance";

export function HomeSlot({ id, children }: { id: HomeSection; children: ReactNode }) {
  const look = useAppearance();
  const place = look.home.indexOf(id);

  return (
    <div
      data-home-section={id}
      hidden={id !== "hero" && look.hide.includes(id) ? true : undefined}
      style={{ order: place < 0 ? 40 : place + 1 }}
    >
      {children}
    </div>
  );
}
