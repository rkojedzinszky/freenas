import asyncio
import os
import sys
import threading
import time
import tempfile

from middlewared.service import Service, private

if '/usr/local/www' not in sys.path:
    sys.path.append('/usr/local/www')
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'freenasUI.settings')

import django
from django.apps import apps
if not apps.ready:
    django.setup()

from freenasUI.common.freenassysctl import freenas_sysctl as _fs
from freenasUI.common.freenasldap import FreeNAS_ActiveDirectory


class ServiceMonitorThread(threading.Thread):
    def __init__(self, **kwargs):
        super(ServiceMonitorThread, self).__init__()
        self.setDaemon(False)

        self.id = kwargs.get('id')
        self.frequency = kwargs.get('frequency')
        self.retry = kwargs.get('retry')
        self.host = kwargs.get('host')
        self.port = kwargs.get('port')
        self.name = kwargs.get('name')
        self.logger = kwargs.get('logger')
        self.middleware = kwargs.get('middleware')
        self.finished = threading.Event()
        # Reset stale alerts
        ServiceMonitorThread.reset_alerts(self.name)

        self.logger.debug("[ServiceMonitorThread] name=%s frequency=%d retry=%d", self.name, self.frequency, self.retry)

    @staticmethod
    def reset_alerts(service):
        for _file in os.listdir('/tmp'):
            if _file.startswith(f'.alert.{service}.') and _file.endswith('.service_monitor'):
                try:
                    os.remove(os.path.join('/tmp', _file))
                except OSError:
                    pass

    @private
    def alert(self, service, message):
        with tempfile.NamedTemporaryFile(
            dir='/tmp', prefix=f'.alert.{service}.', suffix='.service_monitor',
            mode='w', encoding='utf-8', delete=False
        ) as _file:
            _file.write(message)

    @private
    def isEnabled(self, service):
        enabled = False
        #
        # XXX yet another hack. We need a generic mechanism/interface that we can use that tells
        # us if a service is enabled or not. When the service monitor starts up, it assumes
        # self.connected is True. If the service is down, but enabled, and we restart the middleware,
        # and the service becomes available, we do not see a transition occur and therefore do not
        # start the service.
        #
        if service in ('activedirectory', 'ldap', 'nis'):
            ds = self.middleware.call_sync('datastore.query', 'directoryservice.%s' % service)[0]
            if service == 'activedirectory':
                service = 'ad'
            enabled = ds["%s_enable" % service]

        else:
            services = self.middleware.call_sync('datastore.query', 'services.services')
            for s in services:
                if s['srv_service'] == 'cifs':
                    enabled = s['srv_enable']
                # What about other services?

        return enabled

    @private
    def tryConnect(self, host, port):
        max_tries = 3
        connected = False
        sm_timeout = _fs().middlewared.plugins.service_monitor.socket_timeout
        host_list = []

        if self.name == 'activedirectory':

            for i in range(0, max_tries):
                # Make max_tries attempts to get SRV records from DNS
                host_list = FreeNAS_ActiveDirectory.get_ldap_servers(host)
                if host_list:
                    break
                else:
                    self.logger.debug(f'[ServiceMonitorThread] Attempt {i} to query SRV records failed')

            if not host_list:
                self.logger.debug(f'[ServiceMonitorThread] Query for SRV records for {host} failed')
                return False

            for h in host_list:
                port_is_listening = FreeNAS_ActiveDirectory.port_is_listening(str(h.target), h.port, errors=[], timeout=sm_timeout)
                if port_is_listening:
                    return True
                else:
                    self.logger.debug(f'[ServiceMonitorThread] Cannot connect: {h.target}:{h.port}')
                    connected = False

            return connected

        else:
            self.logger.debug(f'[ServiceMonitorThread] no monitoring has been written for {self.name}')
            return False

    @private
    def getStarted(self, service):
        max_tries = 3

        for i in range(0, max_tries):
            if service == 'activedirectory':
                if not self.middleware.call_sync('service.started', 'cifs'):
                    self.logger.debug("[ServiceMonitorThread] restarting Samba service")
                    self.middleware.call_sync('service.start', 'cifs')
            if self.middleware.call_sync('service.started', service):
                return True
            time.sleep(1)

        return False

    def run(self):
        ntries = 0
        service = self.name

        while True:
            self.finished.wait(self.frequency)
            #
            # We should probably have a configurable threshold for number of
            # failures before starting or stopping the service
            #
            if self.finished.is_set():
                # Thread.cancel() takes a while to propagate here
                ServiceMonitorThread.reset_alerts(service)
                return

            connected = self.tryConnect(self.host, self.port)
            started = self.getStarted(service)
            enabled = self.isEnabled(service)

            # Try less disruptive recovery attempt first before restarting AD service
            if not started and service == 'activedirectory':
                self.logger.debug("[ServiceMonitorThread] reloading Active Directory")
                self.middleware.call_sync('service.reload', 'activedirectory')
                started = self.getStarted(service)

            self.logger.trace("[ServiceMonitorThread] connected=%s started=%s enabled=%s", connected, started, enabled)
            # Everything is OK
            if connected and started and enabled:
                # Do we want to reset all alerts when things get back to normal?
                ServiceMonitorThread.reset_alerts(service)
                ntries = 0
                continue

            start_service = False
            stop_service = False
            ntries += 1

            self.alert(service, "attempt %d to recover service %s\n" % (ntries, service))

            if connected:
                if not started:
                    start_service = True
            else:
                if enabled:
                    stop_service = True

            if stop_service:
                self.logger.debug("[ServiceMonitorThread] disabling service %s", service)
                try:
                    self.middleware.call_sync('service.stop', service)
                except Exception:
                    self.logger.debug(
                        "[ServiceMonitorThread] failed stopping service", exc_info=True
                    )

            if start_service:
                self.logger.debug("[ServiceMonitorThread] enabling service %s", service)
                try:
                    self.middleware.call_sync('service.start', service)
                except Exception:
                    self.logger.debug(
                        "[ServiceMonitorThread] failed starting service", exc_info=True
                    )

            if self.retry == 0:
                continue

            if ntries >= self.retry:
                break

        if not connected or not enabled or not started:
            # Clear all intermediate alerts
            ServiceMonitorThread.reset_alerts(service)
            # We gave up to restore service here
            self.alert(service, "Failed to recover service %s after %d tries" % (service, ntries))
            # Disable monitoring here?

    def cancel(self):
        self.finished.set()


class ServiceMonitorService(Service):
    """Main-Class for service monitoring."""

    class Config:
        private = True

    def __init__(self, *args):
        super(ServiceMonitorService, self).__init__(*args)
        self.threads = {}

    async def start(self):
        services = await self.middleware.call('datastore.query', 'services.servicemonitor')
        for s in services:
            thread_name = s['sm_name']
            # Remove stale alerts
            ServiceMonitorThread.reset_alerts(thread_name)

            if not s['sm_enable']:
                self.logger.debug("[ServiceMonitorService] skipping %s", thread_name)
                continue

            self.logger.debug("[ServiceMonitorService] monitoring %s", thread_name)

            thread = ServiceMonitorThread(
                id=s['id'], frequency=s['sm_frequency'], retry=s['sm_retry'],
                host=s['sm_host'], port=s['sm_port'], name=thread_name,
                logger=self.logger, middleware=self.middleware
            )
            self.threads[thread_name] = thread
            thread.start()

    async def stop(self):
        for thread in self.threads.copy():
            thread = self.threads.get(thread)
            await self.middleware.run_in_thread(thread.cancel)
            del self.threads[thread.name]
        self.threads = {}

    async def restart(self):
        await self.stop()
        await self.start()


def setup(middleware):
    asyncio.ensure_future(middleware.call('servicemonitor.start'))
