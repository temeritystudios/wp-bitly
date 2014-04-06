=== Plugin Name ===
Contributors: delayedinsanity, chipbennett
Tags: shortlink, short, link, bitly, url, shortener, social, media, twitter, share
Requires at least: 3.8.1
Tested up to: 3.8.1
Stable tag: 2.0

Use the Bitly API to generate short links for all of your posts and pages instead of the internal http://www.yourdomain.com/?p=1 links WordPress generates.


== Description ==

WP Bitly is one of the quickest and easiest plugins you'll deal with this year.

Give it an authorization token (automatically generated for you by Bitly), tell it which post types you'd like shortlinks generated for, and forget about it! WP Bitly does the rest for you.

Shortlinks are a great way to quickly share posts on social media like Twitter, Instagram and Facebook. Just finished writing an amazing post and want to share that post with your friend? It's a lot easier to text message a shortlink than the entire address.

WP Bitly also provides some insights (via a metabox on your edit post screen) as to how your link is being passed around, and who's clicking on it.


== Installation ==

= Upgrading =

Older versions of WP Bitly used a beta API provided by Bitly.com that required a username and API key. The new version of WP Bitly uses the most up to date Bitly API which only requires a single OAuth token to generate short links.

You will need to upgrade from the WordPress dashboard, and navigate to the *Dashboard >> Settings >> Writing* page to add your new OAuth Token.

= Add New Plugin =

1. From the *Dashboard* navigate to *Plugins >> Add New*
2. Enter "WP Bitly" in the search field
3. Select *Install Now*, click *OK* and finally *Activate Plugin*
4. This will return you to the WordPress Plugins page. Find WP Bitly in the list and click the *Settings* link to configure.
5. Enter your OAuth token, and that's all! You're done!


== Frequently Asked Questions ==

= After installation, do I need to update all my posts for short links to be created? =

No. The first time a shortlink is request for a particular post, WP Bitly will automatically generate one.

= What happens if I change a posts permalink? =

WP Bitly will verify the shortlink when it's requested and update as necessary all on its own.

= Can I include the shortlink in a post? =

Sure can! Just use our handy dandy shortcode `[wpbitly]` and shazam! The shortcode accepts all the same arguments as the_shortlink().

= How do I include a shortlink using PHP? =

`<?php the_shortlink(); // shortcode shweetness. ?>`

*(You don't have to include the php comment, but you can if you want.)*


== Changelog ==

= 2.0 =
* Updated for WordPress 3.8.1
* Updated Bitly API to V3
* Added WP Bitly to GitHub at https://github.com/mwaterous/wp-bitly
= 1.0.1 =
* Fixed bad settings page link in plugin row meta on Manage Plugins page
= 1.0 =
* Updated for WordPress 3.5
* Removed all support for legacy backwards compatibility
* Updated Settings API implementation
* Moved settings from custom settings page to Settings -> Writing
* Enabled shortlink generation for scheduled (future) posts
* Added I18n support.
= 0.2.6 =
* Added support for automatic generation of shortlinks when posts are viewed.
= 0.2.5 =
* Added support for WordPress 3.0 shortlink API
* Added support for custom post types.
= 0.1.5 =
* Bugfix
= 0.1.4 =
* Bugfix
= 0.1.0 =
* Initial release of WP Bitly
