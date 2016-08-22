QUICK INSTALL
=============

There are two installation methods that are available. Follow one of these, then log into your Moodle site as an administrator and visit the notifications page to complete the install.

==================== MOST RECOMMENDED METHOD - Git ====================

If you do not have git installed, please see the below link. Please note, it is not necessary to set up the SSH Keys. This is only needed if you are going to create a repository of your own on github.com.

Information on installing git - http://help.github.com/set-up-git-redirect/

Once you have git installed, simply visit the Moodle mod directory and clone git://github.com/markn86/moodle-mod_customcert.git, remember to rename the folder to customcert if you do not specify this in the clone command

Eg. Linux command line would be as follow -

git clone git://github.com/markn86/moodle-mod_customcert.git customcert

Use git pull to update this repository periodically to ensure you have the latest version.

==================== Download the customcert module. ====================

Visit https://github.com/markn86/moodle-mod_customcert and download the zip, uncompress this zip and extract the folder. The folder will have a name similar to markn86-moodle-mod_customcert-c9fbadb, you MUST rename this to customcert. Place this folder in your mod folder in your Moodle directory.

nb. The reason this is not the recommended method is due to the fact you have to over-write the contents of this folder to apply any future updates to the customcert module. In the above method there is a simple command to update the files.


Customisation
=============

========================= Add Fonts to TCPDF. =========================

For customization, you have a couple of options in the Custom Certificate module. One of the most common customizations is, adding fonts to the Certificate creator.

Custom Certificate uses a pdf creator called TCPDF, which embeds fonts into the PDF file, so that you can view fonts, even though they are not installed on the target machine. This requires you to tell TCPDF which fonts to take.

Importing Fonts into TCPDF is quite simple:

1. Convert the font you want embedded into the tcpdf format (for example using [http://fonts.snm-portal.com][1] or [http://www.xml-convert.com/en/convert-tff-font-to-afm-pfa-fpdf-tcpdf][2])

2. Put the converted files into the folder: `/path/to/moodle/lib/tcpdf/fonts`

3. Use the font in your certificate builder

4. Enjoy

> Please note that inserting a file into the tcpdf folder is considered a "core hack" and might not be available on some commercially hosted systems. Please check your providers agreement on the topic to make sure this is something you're allowed to do.

[1]:	http://fonts.snm-portal.com "http://fonts.snm-portal.com"
[2]:	http://www.xml-convert.com/en/convert-tff-font-to-afm-pfa-fpdf-tcpdf "http://www.xml-convert.com/en/convert-tff-font-to-afm-pfa-fpdf-tcpdf"