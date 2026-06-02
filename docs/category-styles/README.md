# Category Style Mockups

Standalone, static HTML mockups exploring a distinct **cartoonish** visual identity per quiz
category. Open `index.html` in a browser to browse them, then leave feedback.

These are throwaway prototypes for design feedback — they are **not** wired into the Livewire
app. Once a direction is approved, the styling can be mapped into `config/themes.php` and the
player/spectator Blade views.

## How to view

Just open the files in a browser (they use Google Fonts via CDN, so keep internet on):

```
open docs/category-styles/index.html
```

## What's here

| Category | Style direction | Files |
|----------|-----------------|-------|
| **Science** ⚗️ | Playful lab/space — rounded *Fredoka* font, electric cyan/purple/toxic-lime, floating molecule bubbles, glowing radial timer. | `science-player.html`, `science-spectator.html` |
| **History** ⚜ | Friendly antiquity — *MedievalSharp* headings on parchment, sepia/gold/burgundy, wax seal & laurels, chunky scroll card. | `history-player.html`, `history-spectator.html` |
| **Pop Culture** ★ | Comic pop-art — chunky *Bangers* display, hot-pink/cyan/yellow neon, halftone Ben-Day dots, speech-bubble question, POW! bursts. | `pop-culture-player.html`, `pop-culture-spectator.html` |
| **General Knowledge** 💡 | Friendly game-show — rounded *Baloo 2*, indigo/blue stage with classic red/blue/yellow/green answers, floating question marks. | `general-knowledge-player.html`, `general-knowledge-spectator.html` |
| **Geography** 🌍 | Map & adventure — *Baloo 2*, sky/ocean/land palette, map-grid + dashed routes, compass timer, parchment question card. | `geography-player.html`, `geography-spectator.html` |
| **Nature** 🌿 | Forest & wild — organic *Quicksand*, emerald/lime/bark greens, swaying leaves, glowing sun timer, cream sign card. | `nature-player.html`, `nature-spectator.html` |
| **Sports** 🏆 | Stadium energy — athletic *Anton*, red/orange + field-green, motion lines, skewed badges, digital scoreboard timer. | `sports-player.html`, `sports-spectator.html` |

Each category has two screens:

- **Player** — phone-sized: category badge, timer, question card, A–D answer buttons.
- **Spectator** — big-screen: question hero, large answer grid, question counter + answered count.

## Research notes (per-category visual language)

- **Science** — bubbly/atomic, neon "lab" palette (cyan, electric purple, toxic lime). Imagery:
  beakers, atoms, molecules, bubbles. Rounded geometric type. References: Dexter's Lab, portal-green.
- **History** — parchment & sepia, gold leaf, burgundy. Imagery: scrolls, laurels, columns, wax
  seals, hourglass. Medieval-friendly serif. References: Asterix, illuminated manuscripts.
- **Pop Culture** — 80s/90s neon + comic pop-art. Halftone/Ben-Day dots, speech bubbles, stars,
  lightning. Chunky display type. References: Ben-Day comics, vaporwave, Kahoot energy.
- **General Knowledge** — bright, friendly TV game-show. Lightbulb/brain/question-mark imagery,
  confetti, the classic four-color answer quartet. Rounded approachable type.
- **Geography** — explorer/cartography. Globe, compass, map pins, contour grid, dashed travel
  routes, parchment maps. Rounded adventurous type. References: old atlases, Indiana Jones-lite.
- **Nature** — forest/wildlife. Leaves, trees, sun, animals, wood signs, earthy greens + sky blue.
  Soft organic type. References: national-park signage, nature documentaries.
- **Sports** — stadium/broadcast energy. Trophy, balls, whistle, jersey numbers, field stripes,
  motion lines, digital scoreboard. Bold condensed athletic type. References: ESPN, FIFA graphics.

## Giving feedback

Per category, useful to know:
- Does the overall vibe fit?
- Fonts — too much / not enough character?
- Colors — readable, on-brand?
- Anything to borrow across categories (e.g. the chunky button shadow)?
