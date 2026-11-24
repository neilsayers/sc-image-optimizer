=== SC Image Optimizer ===
Contributors: neilsayers
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

Site-agnostic image pipeline: custom image sizes from a settings screen, AVIF/WebP output on upload, an original-image cap, a regenerate button, and a capped srcset helper for lightboxes.

== Description ==

* Lists every registered image size (WordPress, theme, plugins) and lets you add your own with a repeater.
* Converts new JPEG/PNG uploads (and every generated size) to AVIF or WebP when the server supports it. The original upload is always kept.
* Caps oversized originals (default 2560px).
* "Regenerate all images" button — the in-dashboard equivalent of `wp media regenerate --yes`.
* `scio_srcset( $attachment_id, $max_edge = 0 )` template helper: a srcset capped at your "lightbox" size so hi-DPI screens never pull anything bigger.

== Changelog ==

= 0.2.0 =
* Top-level menu, regenerate button, GitHub updates.

= 0.1.0 =
* First release.
