# Clone the repository
git clone https://github.com/pbay1/kasongo.git
cd kasongo

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
npm run production

# Create environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure .env file with your database, Nano RPC, and other settings
nano .env

# Run database migrations
php artisan migrate --seed

# Set up cron jobs for scheduled tasks
(crontab -l 2>/dev/null; echo "* * * * * cd /path/to/kasongo && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# Set up queue worker for background jobs
sudo nano /etc/supervisor/conf.d/kasongo-worker.conf
# Add the following:
[program:kasongo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/kasongo/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/kasongo/storage/logs/worker.log

# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kasongo-worker:*

# Set up Selenium server (for automation)
sudo apt install default-jre
wget https://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar
java -jar selenium-server-standalone-3.141.59.jar -port 4444 &

# Install ChromeDriver for Selenium
wget https://chromedriver.storage.googleapis.com/$(curl -s https://chromedriver.storage.googleapis.com/LATEST_RELEASE)/chromedriver_linux64.zip
unzip chromedriver_linux64.zip
sudo mv chromedriver /usr/bin/chromedriver
sudo chown root:root /usr/bin/chromedriver
sudo chmod +x /usr/bin/chromedriver
