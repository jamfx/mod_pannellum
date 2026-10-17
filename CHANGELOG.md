# Changelog

All notable changes to the Pannellum 360° Viewer module will be documented in this file.

## [1.1.0] - 2026-10-16

### Added

- **Gallery mode**: display multiple panoramas in a configurable, responsive grid (2/3/4 columns, gap, aspect ratio 16:9 / 4:3 / 1:1)
- **Thumbnail generation** with a correct equirectangular-to-perspective projection (PHP GD, bilinear interpolation); thumbnails are created automatically when the module is saved and stored in `images/mod_pannellum/thumbnails/`
- **Large panoramas**: images above 4096×2048 px are downscaled before processing (ImageMagick command line → Imagick extension → GD with memory check), tested with 15520×7760 px drone panoramas
- **Inline and modal/lightbox** viewer modes; only one viewer is active at a time, also across several galleries on one page
- Optional **divider** between gallery items (color and width), configurable **hover effect**, custom **CSS class**
- Display options (height, shadow, compass, auto-rotation) also apply to the gallery
- Full **keyboard navigation and accessibility** (ARIA labels, alt texts, focus handling)
- Automatic **cleanup** of thumbnails that are no longer used; thumbnails are removed on uninstall
- **Debug log** switch (tab "Advanced") for support: writes `logs/mod_pannellum.log.php` without IP addresses
- Notice on installation if the PHP GD extension is missing
- Hint above the gallery entries and a progress overlay while saving (thumbnails are created during save)

### Changed

- Faster downscaling of large JPEG panoramas (about 2.4× faster and a quarter of the memory in tests)
- Module restructured to the current Joomla 5/6 conventions: service provider, dispatcher, helper classes and namespaced form field/rule classes (namespace `Nikomat\Module\Pannellum`); the legacy `mod_pannellum.php` entry file was removed
- All remaining hard-coded texts (field hints, units, JavaScript messages) moved to the language files (German and English)
- "Auto load" now defaults to "No" for new modules
- License headers and metadata unified to GPL v3 or later

## [1.0.1] - 2026-08-29

### Fixed

- **Critical: Multiple module instances** – Using two or more Pannellum modules on the same page caused a 500 error ("Cannot redeclare hexToRgba()"). The global PHP function has been converted to a scoped closure.
- **Auto-Load in Multi-Scene Mode** – The auto-load setting was ignored in multi-scene mode (always forced to "on"). It now respects the configured value.
- **Display options not applied** – Compass, auto-rotation speed, and auto-rotation delay settings had no effect because they were not passed to the Pannellum viewer.

### Added

- **Localized loading text** – The "Loading..." and "Click to Load Panorama" texts are now translatable via Joomla language files (German and English included).

### Changed

- Removed debug `console.log` statements from the frontend JavaScript for cleaner browser console output.

## [1.0.0] - 2026-03-29

### First Public Release

- **Single Panorama Mode** with optional hotspots and image description overlay
- **Multi-Scene Mode** for interactive virtual tours with linked panoramas
- **Visual Hotspot Editor** for placing hotspots by clicking in the panorama
- **Scene Link Hotspots** for navigation between panorama scenes
- **Info Point Hotspots** with rich HTML content support (filtered via Joomla safehtml)
- **Per-Scene Image Descriptions** with customizable position, font size, color, and opacity
- **Font Awesome Icon Support** for hotspots with adjustable color and size
- **Display Options:** configurable height (px/vh), drop shadow, compass, auto-rotation
- **Mobile Gyroscope Support** for device orientation control
- **Fullscreen Mode** with proper z-index management
- **Multilingual:** complete German (de-DE) and English (en-GB) language files
- **Joomla Update Server** via GitHub for automatic update notifications
- Compatible with Joomla 5.x and 6.x (PHP 8.1+)
