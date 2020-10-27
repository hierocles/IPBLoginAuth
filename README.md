# MybbLoginAuth

MybbLoginAuth is an extension for MediaWiki 1.27 and up that allows users to log in using their credentials from a [MyBB](https://www.mybb.com) forum community. The extension validates the username and password with a provided MyBB database, and then creates a local user account on MediaWiki.

It is recommended to disable account creation in MediaWiki. Additionally, password resets must be performed in MyBB and not in MediaWiki, as this extension overrides MediaWiki's authentication to pull only from the provided MyBB database.

MybbLoginAuth is based on [IPBLoginAuth](https://www.github.com/FHannes/IPBLoginAuth) by Frederic Hannes.

# Requirements

* MediaWiki 1.27+
* MyBB 1.8.24+
* MySQL database for MyBB
* PHP 7.0+
* MySQLi PHP extension

# Installation

* Download the extension or use Git to download the extension to your `/extensions` directory.
* Edit database settings in `extension.json` with your MyBB database information.
* Add the following code at the bottom of your `LocalSettings.php`:

    wfLoadExtension( 'MybbLoginAuth' );

# Configuration
## User groups

By default, this extension will set local MediaWiki accounts as (email) confirmed if they are not part of the "Awaiting Validation" usergroup in MyBB. This usergoup ID is typically 5 in default MyBB installations. It can updated as necessary in `extension.json`:

        "MybbGroupValidating": {
            "description": "Awaiting Validation usergroup",
            "value": 5
        },

The extension will assign local MediaWiki users to the `sysop` usergroup if they are part of the MyBB Administrators or Super Moderators usergroup. This usergroup IDs are 4 and 3, respectively, in a default MyBB installation. You can supply a single usergroup ID or multiple ones by using an array of values.

You can also add additional group associations as needed in `extensions.json`:

        "MybbGroupMap": {
            "description": "Mapping of wiki roles to MyBB usergoups. Default Super Moderators (3) and Adminstrators (4)",
            "value": {
                "sysop": [3, 4]
            }
        }

## Account recovery link
This extension does not support account recovery from within MediaWiki. As such, it is highly recommended to disable internal account recovery by adding the following to your `LocalSettings.php`:

    $wgPasswordResetRoutes = ['username' => false, 'email' => false];

## Account creation
The way this extension overrides the MediaWiki authentication, you will not be able to validate local MediaWiki accounts that do not have a corresponding MyBB account. As such, you should disable account creation, but leave auto account creation enabled, by adding the following to your `LocalSettings.php`:

    $wgGroupPermissions['*']['createaccount'] = false;
    $wgGroupPermissions['*']['autocreateaccount'] = true;

You can further disable the Special:CreateAccount page and disable the link from appearing by adding the following to your `LocalSettings.php`. While this isn't a necessity, as long as account creation is disabled as above, it does remove an unnecessary page that make confuse users.

    $wgHooks['SpecialPage_initList'][] = function ( &$list ) {
            unset( $list['CreateAccount'] );
            return true;
    };
    function NoCreateAccountOnMainPage( &$personal_urls ){
        unset( $personal_urls['createaccount'] );
        return true;
    }
    $wgHooks['PersonalUrls'][]='NoCreateAccountOnMainPage';


# License
This extension is licensed under the included GPLv3 license.

# Contributing
Contributions can be made to the plugin by submitting pull requests through its [GitHub repository](https://github.com/hierocles/MybbLoginAuth).
