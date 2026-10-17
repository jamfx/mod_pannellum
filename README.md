# Pannellum 360° Viewer – Joomla Module

A feature-rich Joomla 5 & 6 module for embedding interactive 360° panoramas, powered by the [Pannellum](https://pannellum.org/) library by Matthew Petroff.

**Version:** 1.1.0  
**Author:** Niko Winckel  
**Website:** [nik-o-mat.de](https://nik-o-mat.de)  
**License:** GNU/GPL v3 or later (Module) | MIT (Pannellum library)

## Features

- **Single Panorama Mode** – Display a single 360° image with optional hotspots and image description
- **Multi-Scene Mode** – Create interactive virtual tours with multiple linked panoramas
- **Gallery Mode** – Display multiple panoramas in a responsive grid with configurable columns, aspect ratio, and spacing
  * Inline or Modal/Lightbox viewer activation
  * Server-side thumbnail generation with correct perspective projection
  * Optional divider lines and hover effects
  * Full keyboard navigation and accessibility support
- **Visual Hotspot Editor** – Place hotspots by clicking directly in the panorama (no manual coordinate entry)
- **Two Hotspot Types:**
  - Scene Links – Navigate between panorama scenes
  - Info Points – Display rich HTML content in overlay boxes
- **Per-Scene Image Descriptions** – Customizable overlay text with full styling control (position, font size, color, opacity)
- **Font Awesome Icon Support** – Customizable icons with adjustable color and size for hotspots
- **Display Options:**
  - Configurable height (px or vh)
  - Drop shadow with six parameters (horizontal, vertical, blur, spread, color, opacity)
  - Compass display
  - Auto-rotation with speed and delay settings
- **Mobile Gyroscope Support** – Device orientation control on mobile devices
- **Fullscreen Mode** – With proper z-index management for info modals
- **Multilingual** – Complete German and English language files
- **Secure** – HTML content in hotspot tooltips filtered through Joomla's built-in safehtml filter
- **Responsive Design** – Works across all modern browsers and devices

## Requirements

- Joomla 5.0+ or 6.0+
- PHP 8.1 or higher
- PHP GD extension (required for gallery thumbnail generation)
- Recommended for large panoramas (e.g. drone images above 4096×2048 px): ImageMagick (command line `magick`/`convert`, or the PHP Imagick extension)
- Modern browser with JavaScript enabled

## Installation

1. Download the latest release ZIP from [GitHub Releases](https://github.com/jamfx/mod_pannellum/releases)
2. In Joomla backend, go to **System → Install → Extensions**
3. Upload and install the ZIP file
4. Go to **Content → Site Modules**
5. Click **New** and select **Pannellum 360° Viewer**
6. Configure the module and assign it to your desired menu items

## Quick Start

### Single Panorama
1. Select **Display Mode: Single Panorama**
2. Upload a 360° image (equirectangular format, 2:1 aspect ratio)
3. Adjust initial view (Yaw, Pitch, HFOV) if desired
4. Optionally add hotspots using the visual Hotspot Editor
5. Save and publish

### Multi-Scene Tour
1. Select **Display Mode: Multiple Scenes**
2. Add scenes – each with a unique Scene ID and panorama image
3. Use the **Hotspot Editor** to visually place navigation hotspots
4. Set hotspot type to **Scene Link** and enter the target Scene ID
5. The first scene automatically becomes the starting point

### Gallery

1. Select **Display Mode: Gallery**
2. Configure grid settings (columns, gap, aspect ratio)
3. Add panorama entries with images and titles (use the **+** button below the list)
4. Thumbnails are generated automatically when you save (for large images this can take a few seconds per image; if the server time limit is nearly reached, click **Save** again to continue)
5. Choose viewer mode (Inline or Modal)
6. Save and publish

### Gallery: Recommendations and Limits

- **Image size for the web:** Use panoramas of at most **8192 × 4096 px** on the website. Larger images (e.g. 15520 × 7760 px drone panoramas) load slowly and cannot be displayed as a panorama on many smartphones (WebGL texture size limit). Keep the originals for your archive. The module itself can process large images when creating thumbnails.
- **Entries per gallery:** About **6 to 12 panoramas** per gallery are comfortable for visitors and keep saving fast. More than about **50 entries** can exceed the server limit for form fields (`max_input_vars`, default 1000) – fields would then be silently dropped when saving. Show further panoramas in a second gallery module on another page.
- **Several galleries on one page** are supported; only one viewer is active at a time.
- **Saving:** Thumbnails are created when you save. Large images can take a few seconds each. If a message about the server time limit appears, click **Save** again – existing thumbnails are kept.

### Troubleshooting (Gallery)

Enable **Write Debug Log** in the tab **Advanced** and save again. The module then writes details about the thumbnail generation (method used, duration, server environment) to `administrator/logs/mod_pannellum.log.php`. The file contains no IP addresses. Errors are always logged, even with the switch off.

## Panorama Image Requirements

- **Format:** Equirectangular (spherical) projection
- **Aspect Ratio:** 2:1 (e.g., 4096×2048, 6000×3000, 8192×4096)
- **Coverage:** Complete 360° × 180° view
- **File Types:** JPEG, PNG

## Automatic Updates

This module supports Joomla's built-in update system. After installation, Joomla will automatically check for new versions and notify you when updates are available.

## Support

- **Issues & Feature Requests:** [GitHub Issues](https://github.com/jamfx/mod_pannellum/issues)
- **Releases:** [GitHub Releases](https://github.com/jamfx/mod_pannellum/releases)

## Credits

- **Pannellum Library:** [pannellum.org](https://pannellum.org/) by Matthew Petroff – MIT License
- **Font Awesome:** Icons via Joomla's built-in Font Awesome integration

## License

This module is licensed under the **GNU General Public License v3 or later**.  
The included Pannellum library is licensed under the **MIT License** – see [LICENSE.txt](LICENSE.txt) for details.
