KUSINANG GAMAY — GLOBAL HALL OF FAME SETUP
===========================================

FILES
-----
1. hall-of-fame.php
   - Global leaderboard webpage
   - Score-submission API
   - Top-player JSON API

2. kusinang_gamay_global_hof.sql
   - MySQL/MariaDB table for global scores

3. kusinang_gamay_global_connector.js
   - Exact connector code for your current Kusinang Gamay game
   - Keeps local scores AND syncs finished runs globally
   - Includes an optional global Top 10 replacement for the in-game Hall of Fame modal

SETUP
-----
1. Create a MySQL/MariaDB database in your website hosting panel.
2. Import kusinang_gamay_global_hof.sql in phpMyAdmin.
3. Open hall-of-fame.php and fill in:
      DB_NAME
      DB_USER
      DB_PASS
      DB_HOST (usually localhost)
4. Upload hall-of-fame.php to your website.
5. Keep the game and hall-of-fame.php on the same domain if possible.
6. In kusinang_gamay_global_connector.js, change:
      const KG_GLOBAL_HOF_URL = '/hall-of-fame.php';
   if your leaderboard page uses another path.
7. Paste the connector into the same game script scope where kgRecordRun() exists.
8. Replace the existing kgRecordRun() with the connector's version.
9. Optionally replace kgRenderHall() with the included global version so the game's Hall of Fame modal also shows the worldwide Top 10.

HOW IT WORKS
------------
- A player profile already has an ID and name in your current game.
- At GAME OVER or STORY COMPLETE, kgRecordRun() is already called.
- The connector keeps the current local score record.
- It also sends player ID, name, score, combo, level, mode and run/session ID to hall-of-fame.php.
- The server keeps ONE leaderboard row per player profile and ranks each player's best run.
- Duplicate submissions from the same run do not add another run count.
- If the server is offline, the game's local Hall of Fame still works.

IMPORTANT SECURITY NOTE
-----------------------
This is suitable for a casual public web-game leaderboard, but no browser-only game can make score cheating impossible because players control the JavaScript running on their device. Strong anti-cheat would require server-authoritative gameplay or server-side verification of the run.
