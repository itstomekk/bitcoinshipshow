# Bitcoin Shipshow — Design Guidelines

Quick reference for keeping your game visually consistent with the Bitcoin Shipshow brand. The house look is **"corsair / Blue Ensign"**: a cyanotype-blue parchment, deep marine water, warm gold, and sharp paper-cut edges.

## Palette

| Token | Hex | Use |
|---|---|---|
| Sea (deep marine) | `#061726` | page background, darkest panels |
| Sea mid | `#0C2E4A` | secondary panels, cards |
| Sea lite | `#14486F` | water highlights, borders |
| Ink | `#0B1F33` | text on light, hard shadows, borders |
| Parchment (cyanotype) | `#CBDCE8` | light panels, text on dark |
| Parchment dark | `#A9C3D6` | muted fills |
| Gold | `#D9AE3C` | accents, borders, highlights |
| Signal blue (hot accent) | `#1E6FA6` | CTAs / the "red" slot, recolored blue |

## Typography

- Display / headings: **Pirata One** — ship's-log poster face. File: `assets/PirataOne-Regular.ttf`.
- Body: **"IM Fell English"**, Georgia, or any old-style serif.
- Avoid geometric sans, system-ui, or monospace for body copy.

## Texture (the "old paper" feel)

The site builds its paper look from two ingredients:

1. **Paper grain** — an SVG fractal-noise filter at low opacity with `mix-blend-mode: multiply`. The exact data-URI is in `assets/corsair-reference.css` (`--co-grain`).
2. **Foxing** — three off-centre radial gradients (the brown blotches of aged paper). See `--co-foxing`.

## Shape & layout rules

- **No rounded corners** — everything is sharp (`border-radius: 0`).
- **Bold 4px borders**, usually gold or ink.
- **Hard drop shadows** — `10px 10px 0 0 <ink>` (no blur). This is the signature.
- Flat, layered "cut paper" look. No gradient fills, no glossy 3D, no photorealistic texture.
- Generous empty space; calm, mid-century poster balance.

## Art style anchor

For any generated or illustrated art, match the house style:

> Hybrid paper-cut collage meets vintage woodcut poster. Layered cut-paper shapes with visible edges, bold black woodcut outlines, limited ink palette (deep navy, cream, warm gold, optional warm red), paper grain, mid-century print feel. Minimal detail, generous empty space. No text, no logo, no watermark.

Reference images live in `assets/style-reference/`.

## Assets available

- `assets/PirataOne-Regular.ttf` — display font.
- `assets/bimi-logo.svg` — the brand mark (boat).
- `assets/corsair-reference.css` — full design tokens (grain + foxing data-URIs).
- `assets/style-reference/` — example art in the house style.

## Do / Don't

- **Do** use the palette, Pirata One, sharp corners, and hard shadows.
- **Do** keep it flat and paper-cut if you add art.
- **Don't** bring in neon colors, glassmorphism, rounded UI, or unrelated fonts.
- **Don't** copy the full site chrome — your game is its own page that lives inside the brand.
