import { cn } from "@/lib/utils";

export type SceneFallbackProps = {
  className?: string;
  /** Soft pulse while the GLB scene streams in. */
  loading?: boolean;
};

/** Studio photograph shown until the anatomical GLB is ready, or when WebGL is off. */
export function SceneFallback({ className, loading = false }: SceneFallbackProps) {
  return (
    <div
      aria-hidden
      className={cn(
        "relative flex h-full w-full items-center justify-center",
        className,
      )}
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/visuals/jaw-hero.webp"
        alt=""
        className={cn("h-full w-full object-contain", loading && "animate-pulse")}
      />
    </div>
  );
}
