=== RapID Secure Login ===
Contributors: intercede01
Tags: 2FA, security, login, authentication, fingerprint, Secure, logon, 2 factor authentication
Requires at least: 4.5
Tested up to: 4.8
Stable tag: 2.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

RapID Secure Login (RapID-SL) is a simple and cost effective authentication replacement for Clef.

== Description ==
RapID Secure Login (RapID-SL) is a simple and cost effective authentication replacement for Clef.

Enjoy hassle-free and secure user login to WordPress websites and blogs. RapID-SL combines simplicity with a great user experience, removing the need for vulnerable and inconvenient usernames and passwords.

###Benefits
* 2FA with unrivalled ease of use.
* Up and running in a couple of minutes.
* A great alternative to Clef, with the added advantage of not relying on an external authentication service.
* Doesn't rely on vulnerable and clumsy SMS one-time passwords.
* Use a second phone or tablet as a backup.
* Simple "scan and fingerprint" interface – no need to type anything.
* Fast sign-up to blogs and websites.

###Features
* Easy log-in with your phone: simply scan a QR code using your phone, then provide a fingerprint or PIN – never need complex personal details or passwords again.
* Enterprise-grade cybersecurity technology, using 2048-bit cryptography, trusted by governments and corporations worldwide.
* Direct mobile browser login too – simply tap the on screen QR code for a prompt.
* Easy install: no coding or special knowledge required.
* Customized logon screens supported via simple WordPress "shortcodes".
* Automatic login to multiple sites from multiple devices.

[Download on Google Play](https://play.google.com/store/apps/details?id=com.intercede.rapidsl&hl=en_GB)

[Download on iTunes](https://itunes.apple.com/us/app/rapid-secure-login/id1185934781?mt=8)

== Installation ==

1. Install the plugin directly through the WordPress add plugins admin page.
1. Install the RapID Secure Login app on your phone.
1. Use the app to scan the QR Code in the RapID Secure Login Plugin Settings Page.
1. Follow the instructions in the app to create a RapID account. 

Once registered, you can use your phone to log in to your WordPress site and administer your account through the [RapID dashboard](https://rapidportal.intercede.com/)

Your site must have correctly configured support for OpenSSL. Please check with your hosting service provider if you are unsure.

If your WordPress site uses self-signed certificates to support https (during local development for example), it will not be possible to configure the site correctly, as the plugin will not be able to trust the endpoint where the certificates will be uploaded. The solution would be to use a properly trusted certificate from a recognised certificate authority.

If your website uses a "privacy mode" or basic authentication to protect the WordPress administration area in addition to the normal WordPress login mechanism, it will not be possible for the plugin to configure the site correctly. The "privacy mode" or basic authentication will need to be turned off.

== Frequently Asked Questions ==

= Can I log in to more than one account for each site? =

Yes - when the RapID Secure Login app sees that you have more than one credential on your phone for the site, it lets you choose the one you want.

= What happens if I lose or change my phone? =

At present, it is important that you keep a record of your original password, or that you are able to request an administrator reset from the sites you register with. 

You can also have the app on more than one phone of course! Then you can log in again and re-register with your new phone. We do intend to introduce a secure, managed recovery service in the near future to make this even simpler.

= Can I still log in if the RapID service goes down? =

Yes - the RapID Service is only used for creating your user credentials when you enrol. At the point of authentication, all messages are purely between the app and the WordPress site.

= Can I un-authorize a phone from my accounts? =

Yes - just log in to your account, edit your WordPress profile and you can remove enrolled phones from your account. The site administrator can also do this on your behalf.

= Does RapID know my passwords or user ID? =

No - RapID works independently of your passwords and user ID. You can change those and lock them away off-line!

When you create a RapID credential for your phone, the plugin internally generates a random unique identifier, which it associates with your WordPress ID. This random ID is the only WordPress account information the RapID app or service needs. Your actual ID and password are never sent outside your WordPress site.

= What does the service cost? =

Your first 10 credentials are free, with the option for very cost-effective site security if you want to extend the facility to all of your subscribers. Additional licenses can be purchased through the [RapID web site](https://rapidportal.intercede.com).

= Where can I get technical assistance? =

The [RapID Secure Login Web site and Support Forum](https://forums.intercede.com) helps you to find answers to technical questions and lets site administrators post enquiries and comments.

== Screenshots ==

1. The RapID Secure Login plugin displays a QR code in the WordPress login page for you to scan with the phone app
1. You can use a shortcode to include RapID Secure Login in other templates and pages
1. The display of existing username password fields can be managed by a switch in Settings
1. Users can add and remove their phones from the list of authenticators in their profile page. RapID reports when they were last used. You can add another phone by clicking on the RapID logo.
1. When the registration QR code is shown, just scan it with the app to add your phone.
1. The app is simple to use - just click to scan
1. The app can manage multiple accounts on multiple websites, each with their own anonymized credentials
1. The app maintains a log of your activity

== Changelog ==
= 2.0.8 =
* Roles implemented.
* Improved front end registration and settings page.
* Improved error handling.
* Minor bug fixes.

= 2.0.7 =
* Streamlined Sign up.
* Increased resiliency in processes.
* Minor bug fixes.

= 2.0.6 =
* Session bug fixes.
* Minor bug fixes.

= 2.0.5 =
* Credential storage update.
* Security fixes.
* Minor bug fixes.

= 2.0.4 =
* QR Code image optimisation.
* QR Code refreshing.
* Minor bug fixes.

= 2.0.3 =
* Updated readme.txt

= 2.0.2 =
* Improved upgrade path to not remove files before upgrade.
* Improved error handling for ajax pollers.

= 2.0.1 =
* Updated information on plugin settings page.
* Updated readme.txt.
* Minor bug fixes for front end registration and browser compatibility.

= 2.0.0 =
* Ajax entry points naming standardized
* Migrate to JSON Ajax data throughout
* File-based polling check to avoid full stack load

== Service Platform Requirements ==
* Minimum WordPress version: 4.5
* Minimum PHP version: 5.2.4
* Minimum OpenSSL version: 1.0.2


