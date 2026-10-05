=== Koala CPT Plugin ===
Contributors: websitealchemy
Tags: acf, custom post type, wildlife, maps, forms
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shortcodes and Google Maps for the koala custom post type: public and staff sighting forms, and rescue and release location maps.

== Description ==

* `[public_sighting_form]` - public koala sighting form
* `[koala_sighting_form]` - staff sighting form
* `[koala_location_map]` and `[koala_release_map]` - set the rescue and release location on a koala record

Requires Advanced Custom Fields and a `koala` custom post type with the ACF field groups these shortcodes reference.

== Installation ==

Private repository. Install the release zip once via Plugins > Add New > Upload Plugin, then add a read-only GitHub token to wp-config.php:

`define('KOALA_PLUGIN_GITHUB_TOKEN', 'github_pat_...');`

After that, new tagged versions appear as a normal "Update available" notice on the Plugins page. Enter your Google Maps API key under Settings > Koala Plugin.

== Changelog ==

= 1.1.0 =
* New Settings > Koala Plugin page: Google Maps API key, public sighting form map defaults and bounds, field labels and the phone field toggle.
* The Google Maps API key is now read from that setting instead of being hardcoded. Maps will not load until a key is entered.
* Fixed capitalisation of the "No apparent injury" label.
* GitHub-based automatic updates wired in.
