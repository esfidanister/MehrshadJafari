import { cn } from "@/lib/utils";

export type AnatomyLabels = {
  mandible: string;
  maxilla: string;
  wisdomTooth: string;
  nerve: string;
  sinus: string;
};

const markers = [
  { key: "maxilla", n: 1, x: 190, y: 112 },
  { key: "sinus", n: 2, x: 360, y: 72 },
  { key: "wisdomTooth", n: 3, x: 424, y: 178 },
  { key: "mandible", n: 4, x: 250, y: 306 },
  { key: "nerve", n: 5, x: 330, y: 284 },
] as const;

const upperTeeth = Array.from({ length: 11 }, (_, i) => 112 + i * 30);
const lowerTeeth = Array.from({ length: 10 }, (_, i) => 112 + i * 30);

/** Stylised lateral view of the jaws for patient education (decorative, not diagnostic). */
export function AnatomyDiagram({
  labels,
  className,
}: {
  labels: AnatomyLabels;
  className?: string;
}) {
  return (
    <div className={cn("grid gap-8 md:grid-cols-[minmax(0,1fr)_14rem] md:items-center", className)}>
      <svg
        viewBox="0 0 560 380"
        role="img"
        aria-label={Object.values(labels).join(", ")}
        className="h-auto w-full rounded-xl border border-border bg-surface"
      >
        <defs>
          <linearGradient id="bone" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stopColor="#f2efe8" />
            <stop offset="1" stopColor="#e4dccf" />
          </linearGradient>
        </defs>

        {/* sinus */}
        <ellipse cx="360" cy="76" rx="78" ry="34" fill="#9ccdd0" fillOpacity="0.35" stroke="#397f91" strokeOpacity="0.5" strokeDasharray="4 4" />

        {/* maxilla */}
        <path
          d="M 96 126 C 96 112 110 108 130 108 L 440 108 C 470 108 488 118 492 134 L 492 150 L 440 150 L 96 150 Z"
          fill="url(#bone)"
          stroke="#c9bfae"
        />
        {upperTeeth.map((x) => (
          <rect key={`u-${x}`} x={x - 11} y="150" width="22" height="38" rx="9" fill="#fff" stroke="#d9d3c6" />
        ))}

        {/* mandible */}
        <path
          d="M 100 236 C 100 292 140 322 200 326 L 400 326 C 452 326 478 304 488 254 L 502 150 C 504 134 486 126 470 140 L 456 204 L 440 236 Z"
          fill="url(#bone)"
          stroke="#c9bfae"
        />
        {lowerTeeth.map((x) => {
          const isWisdom = x === 112 + 9 * 30;
          return (
            <rect
              key={`l-${x}`}
              x={x - 11}
              y="196"
              width="22"
              height="42"
              rx="9"
              fill={isWisdom ? "#80664c" : "#fff"}
              fillOpacity={1}
              stroke={isWisdom ? "#5c4632" : "#d9d3c6"}
            />
          );
        })}

        {/* inferior alveolar nerve canal */}
        <path
          d="M 470 214 C 450 284 400 294 330 294 L 190 294 C 150 294 132 278 128 258"
          fill="none"
          stroke="#397f91"
          strokeWidth="3"
          strokeDasharray="7 6"
          strokeLinecap="round"
        />

        {markers.map((m) => (
          <g key={m.key}>
            <circle cx={m.x} cy={m.y} r="13" fill="#182832" />
            <text
              x={m.x}
              y={m.y + 4.5}
              textAnchor="middle"
              fontSize="13"
              fontWeight="600"
              fill="#fff"
            >
              {m.n}
            </text>
          </g>
        ))}
      </svg>

      <ol className="flex flex-col gap-3">
        {markers.map((m) => (
          <li
            key={m.key}
            className="flex items-center gap-3 border-b border-border/80 pb-3 text-sm text-text"
          >
            <span
              aria-hidden
              className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-text text-xs font-semibold text-white"
            >
              {m.n}
            </span>
            {labels[m.key]}
          </li>
        ))}
      </ol>
    </div>
  );
}
