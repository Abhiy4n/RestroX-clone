# Project Instructions

- Keep changes focused and follow the conventions of the backend or frontend area being changed.
- After completing a task, keep [docs/PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md) accurate: add a one-line purpose for new project-owned files, update moved or repurposed entries, and remove entries for deleted files. Keep descriptions factual and minimal.
- The structure map covers maintained application source, project configuration, tests, and documentation. Do not list dependencies, generated/build output, caches, runtime data, or local secrets.
- Do not add extra abstractions or dependencies unless the task needs them.

## UI/UX standards

Apply these standards to every agent's frontend work. Treat the Silver Dashboard in `demo.html` as the primary visual reference. Make interfaces polished, responsive, intentional, and consistent while preserving existing design systems and approved components.

### Visual design

- Prefer minimalism, clear hierarchy, usability, and accessibility over decoration or novelty.
- Use Plus Jakarta Sans unless the project defines another approved font. Prefer Lucide icons with consistent sizes and stroke widths.
- Favor restrained neutral colors, silver-gray surfaces, dark-gray typography, subtle borders, consistent spacing, and modest corner radii (6–12px for controls; 12–16px for larger surfaces).
- Use subtle shadows only when they clarify elevation. Avoid excessive gradients, glass effects, large shadows, bright decoration, and unnecessary UI.
- Keep layouts compact but comfortable for professional dashboards. Do not remove necessary functionality in the name of minimalism.

### Interaction and motion

- Give interactive components suitable hover, pressed, focus, selected/active, and disabled states. Use subtle hover feedback; press feedback may use a slight color change or scale of 0.98–0.99.
- Keep keyboard focus clearly visible without relying on hover. Disabled controls must look and behave unavailable; do not rely on opacity alone.
- Animate only when it improves feedback or clarity. Use shared durations: quick feedback 100–150ms, hover 150–180ms, popovers 180–240ms, modals/drawers 200–280ms, and content 160–240ms. Prefer `cubic-bezier(0.22, 1, 0.36, 1)`.
- Prefer opacity and transform. Avoid bounce, excessive scaling, parallax, delayed interactions, and unnecessary animation of large sets of elements.
- Respect `prefers-reduced-motion`. Prefer CSS transitions for simple effects; add no animation dependency unless its complexity is justified.

### Component behavior

- **Search and inputs:** Give focus subtle feedback. Support relevant keyboard shortcuts and navigation; close search overlays on Escape. Show results smoothly when useful and loading only during real async work. Keep validation concise, preserve entered values on errors, and avoid layout shifts.
- **Dropdowns and popovers:** Use a short fade and slight vertical movement. Close on outside click and Escape; support keyboard navigation, manage focus, and stay within the viewport. Rotate chevrons when appropriate. Closing must not block interaction.
- **Forms and CRUD:** Show pending state while saving and prevent duplicate submissions. Report concise success only after completion, and surface errors. Confirm destructive actions when appropriate. Do not remove records before successful deletion unless a safe optimistic update supports rollback.
- **Tables:** Use calm row hover and clear selection states. Animate filtering or pagination only when useful. Use stable skeletons for meaningful waits; avoid unnecessary animation of large result sets.
- **Async UI:** Account for loading, empty, success, and error states, with retry where appropriate. Use skeletons for substantial regions and compact spinners for small actions. Never add artificial delays or misleading success feedback.
- **Navigation:** Keep headers and sidebars consistent, route changes responsive, and browser navigation behavior intact. Avoid full-page fades when only content changes; preserve scroll position when appropriate and prevent layout shifts.

### Existing UI and completion checks

Before changing an existing component, inspect its current design and behavior. Preserve approved layout, colors, typography, spacing, and unrelated behavior; add feedback within the established system. Do not add dependencies without a clear reason.

Before completing frontend work, verify relevant interaction states, overlay open/close behavior, async loading/empty/error/success states, keyboard access, desktop/tablet/mobile layouts, reduced-motion support, stable layout, and truthful working interactions. The result should feel calm, precise, premium, responsive, and cohesive; motion should be felt as smoothness, not spectacle.
