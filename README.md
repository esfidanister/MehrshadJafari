# JAFARI — Surgical Art & Precision

Premium bilingual (FA/EN) personal-brand website for **Dr. Seyed Mehrshad Jafari**, Oral & Maxillofacial Surgeon.

- Next.js App Router · TypeScript · Tailwind CSS v4  
- React Three Fiber cinematic mandible · GSAP-ready motion · Framer Motion · Lenis  
- Static export for **cPanel / Apache** (no Node server required)

## Quick start

```bash
npm install
npm run dev
```

Open [http://localhost:3000/fa/](http://localhost:3000/fa/).

## Build for cPanel

```bash
# optional: set your live domain before build (canonical + sitemap)
# PowerShell:  $env:NEXT_PUBLIC_SITE_URL="https://your-domain.ir"

npm run build:cpanel
```

Upload the **contents** of `out/` to `public_html`. See [DEPLOY-CPANEL.md](./DEPLOY-CPANEL.md).

Optional contact form mailto:

```bash
$env:NEXT_PUBLIC_CONTACT_EMAIL="clinic@example.com"
```

## Routes

| Path | Description |
|------|-------------|
| `/fa/`, `/en/` | Homepage (3D hero + sections) |
| `/fa/about`, `/en/about` | Doctor profile |
| `/fa/services/...` | Treatments + detail pages |
| `/fa/anatomy` | Interactive 3D anatomy |
| `/fa/reviews` | Patient reviews (Nobat.ir sourced) |
| `/fa/articles/...` | Educational journal |
| `/fa/contact`, `/fa/appointment` | Contact & booking links |

## Content notes

Professional claims, addresses and phone numbers are marked **reported / needs confirmation** where public sources conflict. Confirm with the doctor before publishing final credentials or clinic details.

Instagram (reported): [@dr.s.m.jafari](https://www.instagram.com/dr.s.m.jafari/) · Medical code: **141130**
