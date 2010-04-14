=== Plugin Name ===
Contributors: mwaterous
Donate Link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9847234
Tags: short, link, bitly, url, shortener, social, media, twitter
Requires at least: 2.8
Tested up to: 2.9.2
Stable tag: 0.1.4

WP Bit.ly uses the Bit.ly API to generate short links for all of your posts and pages. Statistics are displayed for each link from the dashboard.

== Description ==

WP Bit.ly allows you to generate short links using the Bit.ly API for all of your blogs posts and pages.

The generated short links can than be used by you, your visitors and a variety of other services that employ them. Using shortcode or PHP template tags, the short links can than be displayed directly on your pages so that people can use them for bookmarks, email, twitter, or other social media sites to link back to your pages.

Future development will include the ability to use your own domain as the short link (http://yourdomain.com/bXhGjs).

Features of the current version also include the generation of a new meta box on your posts that show you statistics about your link. In addition to a regular statistics plugin you can use this plugin to see who's clicking on your links!

== Installation ==

Installation of WP Bit.ly is as easy as:

1. Upload the entire `/wp-bitly/` folder to your `/wp-content/plugins/` directory.
1. Navigate to the 'Plugins' page of your dashboard, and activate WP Bit.ly
1. After activation proceed to the WP Bit.ly options page and configure your Bit.ly username and API key.
1. A new metabox will appear on the options page to generate short links for all your existing posts and pages.
1. New posts will automatically generate shortlinks as you create them!

== Frequently Asked Questions ==

= After installation, do I need to update all my posts for short links to be created? =

No, WP Bit.ly can do this for you automatically through the options page. Select the type of post you would like to generate short links for, and click 'Generate', and WP Bit.ly will take care of the rest!

= What happens if I change a posts permalink? =

WP Bit.ly validates all short links whenever you update a post, so if you change the permalink or location of the post, your old short link will be replaced with a new one.

= Does WP Bit.ly conform to the HTML/HTTP shortlink specification? =

Definitely. WP Bit.ly not only adds a `rel=` link to every pages header, but it also inserts the `Link:` specification into your pages HTTP headers. The specification can be found [here](http://purl.org/net/shortlink "HTML/HTTP shortlink specification")

= How do I include the short links using WordPress shortcode? =

The syntax for including a short link is `[wpbitly text="my shortlink!"]`. Setting 'text' will change the text that the link displays publicly. If you'd prefer it to just display the link itself, simply omit 'text', ie `[wpbitly]`

= How do I include the short links using PHP? =

Similar to the shortcode above, you can use `<?php wpbitly_print(); ?>`. This function can be used as is to display the link as is, or you can optionally include up to three arguments, text, echo and pid.

Setting the first argument (text) will change the output of the link text to whatever you choose. The second is a boolean true or false; setting this to true echos the output, and false will return it for use later (default: true). Setting the pid will tell it what post ID you want to display the short link for, and defaults to the current post.

= I want WP Bit.ly to... =

Feature requests are more than welcome! Please visit me on [my blog](http://mark.watero.us/ "Mark.") and contact me either through the available forms or via a comment. I will give consideration to all feedback I recieve!

= I don't think WP Bit.ly should... =

Bug reports (or Oops reports as I call them) can be filed through a [dedicated page](http://mark.watero.us/wordpress-plugins/oops/ "Oops Reports") on [my blog](http://mark.watero.us/ "Mark.").

== Changelog ==

= 0.1.4 =
* Fixed a bug in the short link generation for existing posts and pages

= 0.1.0 =
* Initial release of WP Bit.ly
