# atwebtech browser extension

Chrome / Edge / Brave extension (Manifest V3). Click the toolbar icon, or right-click a page, link or selected text, to open the atwebtech composer with it filled in.

- Permissions: `activeTab` (read the current tab's address and title when clicked) and `contextMenus`. It reads nothing else and sends nothing anywhere; it only opens an atwebtech tab.
- Try it: open `chrome://extensions`, turn on Developer mode, click **Load unpacked** and pick this folder.
- Publish: zip the folder contents and upload to the Chrome Web Store developer dashboard (one-time $5 developer fee). Use the description from `manifest.json`, the 128 px icon, and screenshots of the composer. Edge Add-ons accepts the same zip for free.

This folder is not deployed to the website (see `.deployignore`).
