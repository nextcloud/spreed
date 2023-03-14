#
# @copyright Copyright (c) 2023, Daniel Calviño Sánchez (danxuliu@gmail.com)
#
# @license GNU AGPL version 3 or any later version
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

import argparse
import logging

from .Config import config
from .Server import app

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("-c", "--config", help="path to configuration file", default="server.conf")
    args = parser.parse_args()

    config.load(args.config)

    logging.basicConfig(level=config.getLogLevel())
    logging.getLogger('werkzeug').setLevel(config.getLogLevel())

    listen = config.getListen()
    host, port = listen.split(':')

    app.run(host, port, threaded=True)

if __name__ == '__main__':
    main()
