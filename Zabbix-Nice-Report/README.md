# My zabbix report plugin
This plugin is a php script that makes a request to the monitoring system database and converts it into a csv / xls file. The key part of this plugin is the PHPExel project and a number of scripts written with the kind help of colleagues, although some points were borrowed from open sources.

## Installation

Clone the repository from GitHub. Copy the receiving files from the appropriate working directory of the web server and publish it.

Dependencies required for these scripts: Zabbix 3.0 (may be later).

## Usage

If you put published this plugin in /var/www/html/report (for example) type http://your_zabbix.server/report and use it!

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update the tests as appropriate.

## License

Mostly GPL and MIT I suppose.
