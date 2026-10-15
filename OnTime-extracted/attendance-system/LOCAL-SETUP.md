# ComLab shared setup

Use one designated computer as the host. It runs Apache, PHP, and MySQL; every other ComLab computer opens the host's website in a browser. All users share the host's database. Do not import a separate copy of the database on each client PC.

## Set up the host computer

1. Install XAMPP on the host if needed.
2. Copy this entire `attendance-system` folder to `C:\xampp\htdocs\attendance-system` on the host.
3. Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. On the host, open `http://localhost/phpmyadmin/`, select **Import**, choose `attendance-system\database\attendance_system.sql`, and import it once. Importing this schema again drops and recreates the application's tables, erasing its data.
5. In Windows Firewall, allow Apache (`httpd.exe`, normally `C:\xampp\apache\bin\httpd.exe`) on the **Private** network only. Do not create a public-network or internet-facing firewall rule.
6. Run `ipconfig` on the host and note its IPv4 address on the ComLab network. Ask the school's network administrator to reserve that address for the host if possible.
7. Test on the host at `http://localhost/attendance-system/`.

## Connect the client computers

On each client, open `http://HOST-IP/attendance-system/` in a browser, replacing `HOST-IP` with the host's IPv4 address (for example, `http://192.168.1.25/attendance-system/`). Clients need to be on the same school LAN and do not need their own XAMPP installation or database.

The `.htaccess` file permits loopback and private-network addresses only. For tighter access, replace the private ranges in that file with the specific ComLab subnet provided by the network administrator. This is a classroom LAN setup, not a secure public-internet deployment; use only on a trusted school network and protect the host PC.

Keep the application under the host's XAMPP `htdocs` folder so Apache can execute PHP. Opening PHP files directly from the flash drive will not run the system. Keep a separate backup of the host's database; the flash drive's SQL schema is not a backup of data entered later.
