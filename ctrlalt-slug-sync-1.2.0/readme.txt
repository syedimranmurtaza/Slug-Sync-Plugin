=== CtrlAlt Slug Sync (Old → New) ===
Contributors: ctrlaltimran
Requires at least: 5.5
Tested up to: 6.7
Stable tag: 1.2.0
License: GPLv2 or later

Safely update selected slugs on the site to your desired values, and fix internal links in content, Elementor data, and menu custom URLs. You can define unlimited mappings and run them with a progress log.

== Description ==
This tool is designed for site migrations, where a new site was built with different slugs, and you now want certain key pages and posts to reuse the old URLs.

It:

* Lets you define unlimited mappings in the format "current-slug,desired-slug".
* Changes slugs from the current value to the desired value.
* Updates internal links in post content, Elementor data (_elementor_data), and menu item URLs for those posts/pages.
* Runs only from Tools → CtrlAlt Slug Sync with a button and progress log.

== Installation ==
1. Upload the ZIP via Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to Tools → CtrlAlt Slug Sync.
4. Add or edit mappings, save, then click "Run Slug Sync".

== Safety ==
Always take a full database backup before running slug updates on a live site.

== Changelog ==
= 1.2.0 =
* Stable admin UI with editable mappings textarea.
* Supports any number of slug mappings.
= 1.0.0 =
* Initial release with basic slug syncing.
