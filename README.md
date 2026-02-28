# Custom certificate activity (mod_customcert)

This activity allows dynamic generation of PDF certificates with full customisation in your browser.

## Requirements

- A supported Moodle version (use the plugin release/branch that matches your Moodle version).
- PDF support is provided via Moodle’s built-in PDF library; no extra system packages are normally required.

## Installation

Install **either** via Git **or** by downloading a zip. After installing, log in as an administrator and visit **Site administration → Notifications** to complete the installation.

### Option A: Install with Git (recommended)

1. Go to your Moodle `mod/` directory:

   ```bash
   cd /path/to/moodle/mod
   ```

2. Clone the plugin into a folder called `customcert`:

   ```bash
   git clone https://github.com/mdjnelson/moodle-mod_customcert.git customcert
   cd customcert
   ```

3. Check out the branch that matches your Moodle version.

   The plugin branches follow the Moodle convention `MOODLE_XX_STABLE` (for example: `MOODLE_401_STABLE`).

   ```bash
   git checkout MOODLE_XX_STABLE
   ```

4. To update later:

   ```bash
   git pull
   ```

> Tip: If you’re not sure which branch you need, list available branches:
>
> ```bash
> git branch -r
> ```

### Option B: Download the zip

1. Visit the Moodle plugins directory and download the version that matches your Moodle release:
   - <https://moodle.org/plugins/mod_customcert>
2. Extract the zip.
3. Copy the extracted `customcert` folder into your Moodle `mod/` directory so the path becomes:
   - `moodle/mod/customcert`
4. Log in as an administrator and visit **Site administration → Notifications**.

## Upgrading

### If installed via Git

```bash
cd /path/to/moodle/mod/customcert
git pull
```

If you also upgrade Moodle and need a different branch, switch branches first, then pull:

```bash
git checkout MOODLE_XX_STABLE
git pull
```

### If installed via zip

Download the new zip version that matches your Moodle version, replace the `mod/customcert` folder, then visit **Site administration → Notifications**.

## More information

- Documentation / wiki: <https://docs.moodle.org/en/Custom_certificate_module>

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
