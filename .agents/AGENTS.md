# UI Design Rules

- **Standard Dropdowns (Reuse but do not reinvent)**: NEVER use standard HTML `<select>` for primary UI elements. Always reuse our ultra-premium Alpine.js dropdowns:
  1. **Inline Status Dropdown** (Reference: Complaints page): Color-coded pill button with a floating Alpine list. Parent must dynamically handle `z-index` when open.
  2. **Filter/Category Dropdown** (Reference: Expenses page): Floating pill with an icon, label, and floating `list-group`. Parent wrapper uses high `z-index`.

- **Standard Modals & Dialogs**: Whenever you need to build or modify modal dialogs, you MUST read and follow the specifications documented in [ui_design_guidelines.md](file:///C:/Users/viraj/.gemini/antigravity-ide/brain/a8adb2a1-aba7-4a90-9d76-4636ac8f46ab/ui_design_guidelines.md). Ensure that all modal dialogs utilize the clean, teleported form layout structure defined there.

# Git Commits & Amendments Rules

- **Do Not Autocommit**: NEVER run `git commit` automatically without asking the user first.
- **Commit or Amend Choice**: When changes are ready, explicitly ask the user if they want to create a new commit or amend the last commit. Minor fixes should generally be amended to the last commit to avoid cluttering git history with minor/wip messages.

- **Entry Animations**: Every element on every page MUST have smooth cascading entry animations upon loading or tab changes (e.g., staggered `fadeUp` animations). Components should never abruptly appear or disappear without smooth transitions.
