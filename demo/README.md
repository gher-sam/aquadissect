# Run the local AquaDissect demo on Windows

1. Download and extract the project ZIP to a new folder.
2. Install PHP with PDO SQLite, Composer, and Node.js with npm. Use the versions supported by the project dependencies.
3. Open PowerShell in the extracted project folder and run the launcher.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-local-demo.ps1
```

4. Keep PowerShell open. Visit [the local sign-in page](http://127.0.0.1:8000/login) after the server starts.
5. Enter **admin@aquadissect.local** and **demo123**.

The first run installs dependencies, builds the application, and copies the included SQLite database into the local runtime folder. The app installs the bundled lesson catalog on the first request. Demo changes are saved locally for later runs. Use a fresh extraction if you already configured this project with an `.env` file.

The database contains three synthetic accounts and migration records. Use the demo credentials only on the loopback URL shown by the launcher. The Rabbitfish lesson and clips can be inspected from the administrator workspace under **Species Content → Preview draft**.
