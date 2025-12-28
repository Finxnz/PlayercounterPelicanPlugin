#  Player Counter Plugin

This plugin adds a **player overview section** to your server by using the query system (e.g. for Paper servers).

##  Requirements

* A Pelican Panel with the version beta30 or newer
* A Minecraft **Paper** / **Purpur** / **Leaf** server
* Query support enabled
* Access to the panel server via SSH

---

##  Quick Installation and Update

1. Run the **command**.
2. And choose your option update/install/fix.
3. Use the fix option if the plugin doesnt work or you get permission errors.
4. If you updated the plugin run the script again and choose install. :D

   ```bash
   bash <(curl -fsSL https://raw.githubusercontent.com/Finxnz/PlayercounterPelicanPlugin/refs/heads/master/install.sh)
   ```
5. If this succesfully worked skip the next step.
   
---

##  Installation

1. Upload the plugin **ZIP file** to the **Plugin Section**.
2. After uploading, click the **three dots (⋮)** next to the plugin.
3. Select **Install**.
4. Connect to your server (e.g. via SSH).
5. Run the following command:

   ```bash
   php artisan migrate
   ```

---

##  Configuration

### 1. Create a Query in the Admin Panel

1. Go to your **Admin Panel**.
2. Navigate to **Queries**.
3. Create a new query with the following settings:

   * **Type:** `game type` currentyl supports minecraft only
   * **Offset:** `0` means leave blank
   * **Server:** Choose the server you want a player counter for (currently only supports minecraft)

### 2. Server Settings

1. Open your server in the panel.
2. Go to **Server Properties**.
3. Set the **Query Port** to the port you want to use.
4. Make sure **Query is enabled**.
5. Save the changes.
6. **Restart the server**.

---

##  Result

After restarting, a new **Player section** should appear in the panel where your console is showing the currently online players.

---

##  Done

The plugin is now ready to use.
Enjoy! 

