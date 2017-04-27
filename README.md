# The custom certificate activity

This activity allows the dynamic generation of PDF certificates with complete customisation via the web browser.

## Installation

There are two installation methods that are available. 

Follow one of these, then log into your Moodle site as an administrator and visit the notifications page to complete the install.

### Git

This requires Git being installed. If you do not have Git installed, please visit the [Git website](https://git-scm.com/downloads "Git website").

Once you have Git installed, simply visit your Moodle mod directory and clone the repository using the following command.

<code>git clone https://github.com/markn86/moodle-mod_customcert.git customcert</code>

Then checkout the branch corresponding to the version of Moodle you are running using the following command.

Note - replace MOODLE_32_STABLE with the version of Moodle you are using.

<code>git checkout MOODLE_32_STABLE</code>

Use <code>git pull</code> to update this repository periodically to ensure you have the most recent updates.

### Download the zip

Visit the [Moodle plugins website](https://moodle.org/plugins/mod_customcert "Moodle plugins website") and download the zip corresponding to the version of Moodle you are using. Extract the zip and place the 'customcert' folder in the mod folder in your Moodle directory.

## More information

Please visit the [wiki page](https://docs.moodle.org/en/Custom_certificate_module "Wiki page") for more details. Please feel free to edit it. :)

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).