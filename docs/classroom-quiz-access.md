# Rabbitfish quiz access on a classroom network

## Release the approved test pair

Faculty approval and administrator activation are separate recorded actions.
Approval alone does not display a test pair to students.

1. Sign in as an administrator on the laptop that hosts the classroom application.
2. Confirm that the reviewed Rabbitfish lesson version is published and assigned
   to the students' active section. Confirm their existing enrollments are active.
3. Open **Class sections** and find the correct Rabbitfish pretest/posttest pair,
   instrument version, section and lesson version.
4. If its status is **approved**, select **Activate approved tests**, then confirm.
   A draft still needs independent faculty approval with the required evidence.
5. Students sign in on the same running application and open **My class**. The
   pretest appears there. The posttest requires a saved pretest and a completed
   formative lesson for that exact section and Rabbitfish version.

A quiz approved on a different installation is not automatically copied to this
laptop. The bundled download supplies synthetic demo accounts and draft content;
it does not contain a real class database or actual faculty approval records.
Do not recreate approval evidence or reset an existing database to make a quiz
appear. Transfer authorized class data privately when needed.

## Share the configured laptop application

Use the existing configured application and database. The bundled
`start-local-demo.ps1` launcher listens only on the host laptop and is for local
preview; it does not share the application with classmates.

For Laravel's development server on a classroom laptop:

1. Connect the host laptop and student devices to the same Wi-Fi network. Find
   the laptop's current IPv4 address with `ipconfig`.
2. In the host's private `.env`, set `APP_URL` to its network address and chosen
   port. For example, `http://192.168.1.20:8000` is an example address only.
   Set `ASSET_URL` to the same reachable address, or leave it unset for assets
   served from the current origin. A student-facing build must not use
   `localhost` or `127.0.0.1` as its asset address.
3. From the project folder, rebuild and start the server:

   ```powershell
   php artisan config:clear
   npm.cmd run build
   php artisan serve --host=0.0.0.0 --port=8000 --tries=1
   ```

4. Keep the server running. Permit that server on the Windows private network if
   Windows requests it. Give students the laptop's actual network URL, including
   its port and `/login`. `localhost` on a student's phone refers to the phone.
5. Verify sign-in and an activated synthetic quiz from another device before
   inviting the class. If that URL cannot load, check the host firewall, correct
   address, and Wi-Fi client isolation before troubleshooting quiz status.

An Apache/Laragon installation may instead use an existing network-accessible
virtual host. Its document root must be the project's `public` directory and its
listener and access rules must allow the classroom network. A loopback-only
localhost alias cannot be used from student devices.

## Browser compatibility and records

HTTP network addresses do not expose the browser's `crypto.randomUUID()` method.
Online pretest/posttest, formative submissions, Safety & Tools submissions and
notebook revisions now share a UUID generator that uses cryptographic random
bytes when the native method is unavailable. Server grading, UUID validation,
retry handling, one scored attempt per phase and all existing access gates remain
in force. Downloaded offline packages and service workers still require a
supported secure origin; this change does not enable them on ordinary LAN HTTP.

When a student belongs to multiple sections using the same package, the lesson
submission now retains the chosen section during its content permission check.
Completion in one section does not unlock the other section's posttest.

No account, enrollment, lesson package or recorded attempt is migrated, removed
or reseeded by this correction. Faculty-dependent material remains disabled until
the existing approval, publication, assignment and activation requirements pass.

Engineering browser checks use synthetic accounts. They cannot confirm the
approval, activation or enrollment state of a separate laptop's database, and
they do not replace a physical student-device acceptance check.
