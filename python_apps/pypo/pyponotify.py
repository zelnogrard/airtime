# -*- coding: utf-8 -*-
import traceback

"""
Python part of radio playout (pypo)

This function acts as a gateway between liquidsoap and the server API.
Mainly used to tell the platform what pypo/liquidsoap does.

Main case:
 - whenever LS starts playing a new track, its on_metadata callback calls
   a function in ls (notify(m)) which then calls the python script here
   with the currently starting filename as parameter
 - this python script takes this parameter, tries to extract the actual
   media id from it, and then calls back to the API to tell about it about it.

"""

from optparse import OptionParser
import sys
import logging.config
import json

# additional modules (should be checked)
from configobj import ConfigObj

# custom imports
#from util import *
from api_clients import *
from std_err_override import LogWriter

# help screeen / info
usage = "%prog [options]" + " - notification gateway"
parser = OptionParser(usage=usage)

# Options
parser.add_option("-d", "--data", help="Pass JSON data from Liquidsoap into this script.", metavar="data")
parser.add_option("-m", "--media-id", help="ID of the file that is currently playing.", metavar="media_id")
parser.add_option("-e", "--error", action="store", dest="error", type="string", help="Liquidsoap error msg.", metavar="error_msg")
parser.add_option("-s", "--stream-id", help="ID stream", metavar="stream_id")
parser.add_option("-c", "--connect", help="Liquidsoap connected", action="store_true", metavar="connect")
parser.add_option("-t", "--time", help="Liquidsoap boot up time", action="store", dest="time", metavar="time", type="string")
parser.add_option("-x", "--source-name", help="source connection name", metavar="source_name")
parser.add_option("-y", "--source-status", help="source connection status", metavar="source_status")
parser.add_option("-w", "--webstream", help="JSON metadata associated with webstream", metavar="json_data")
parser.add_option("-n", "--liquidsoap-started", help="notify liquidsoap started", metavar="json_data", action="store_true", default=False)
parser.add_option("--auto-dj-trigger", help="poke server to check if autodj needs to add a song", metavar="autodj_trigger", dest="autodj_trigger", action="store_true")


# parse options
(options, args) = parser.parse_args()

# configure logging
logging.config.fileConfig("notify_logging.cfg")
logger = logging.getLogger('notify')
LogWriter.override_std_err(logger)

#need to wait for Python 2.7 for this..
#logging.captureWarnings(True)

# loading config file
try:
    config = ConfigObj('/etc/airtime/pypo.cfg')

except Exception, e:
    logger.error('Error loading config file: %s', e)
    sys.exit()


class Notify:
    def __init__(self):
        self.api_client = api_client.AirtimeApiClient(logger=logger)

    def notify_liquidsoap_started(self):
        logger.debug("Notifying server that Liquidsoap has started")
        self.api_client.notify_liquidsoap_started()

    def notify_media_start_playing(self, media_id):
        logger.debug('#################################################')
        logger.debug('# Calling server to update about what\'s playing #')
        logger.debug('#################################################')
        response = self.api_client.notify_media_item_start_playing(media_id)
        logger.debug("Response: " + json.dumps(response))

    # @pram time: time that LS started
    def notify_liquidsoap_status(self, msg, stream_id, time):
        logger.debug('#################################################')
        logger.debug('# Calling server to update liquidsoap status    #')
        logger.debug('#################################################')
        logger.debug('msg = ' + str(msg))
        response = self.api_client.notify_liquidsoap_status(msg, stream_id, time)
        logger.debug("Response: " + json.dumps(response))

    def notify_source_status(self, source_name, status):
        logger.debug('#################################################')
        logger.debug('# Calling server to update source status        #')
        logger.debug('#################################################')
        logger.debug('msg = ' + str(source_name) + ' : ' + str(status))
        response = self.api_client.notify_source_status(source_name, status)
        logger.debug("Response: " + json.dumps(response))

    def notify_webstream_data(self, data, media_id):
        logger.debug('#################################################')
        logger.debug('# Calling server to update webstream data       #')
        logger.debug('#################################################')
        response = self.api_client.notify_webstream_data(data, media_id)
        logger.debug("Response: " + json.dumps(response))
        
    def notify_autodj_trigger(self):
        logger.debug('####################################################################')
        logger.debug('# Poking server to check if autodj needs to do what it needs to do #')
        logger.debug('####################################################################')
        self.api_client.notify_autodj_trigger()

    def run_with_options(self, options):
        if options.error and options.stream_id:
            self.notify_liquidsoap_status(options.error, options.stream_id, options.time)
        elif options.connect and options.stream_id:
            self.notify_liquidsoap_status("OK", options.stream_id, options.time)
        elif options.source_name and options.source_status:
            self.notify_source_status(options.source_name, options.source_status)
        elif options.webstream:
            self.notify_webstream_data(options.webstream, options.media_id)
        elif options.media_id:
            self.notify_media_start_playing(options.media_id)
        elif options.liquidsoap_started:
            self.notify_liquidsoap_started()
        elif options.autodj_trigger:
            self.notify_autodj_trigger()
        else:
            logger.debug("Unrecognized option in options(%s). Doing nothing" \
                  % str(options))


if __name__ == '__main__':
    print
    print '#########################################'
    print '#           *** pypo  ***               #'
    print '#     pypo notification gateway         #'
    print '#########################################'

    # initialize
    try:
        n = Notify()
        n.run_with_options(options)
    except Exception as e:
        print( traceback.format_exc() )

