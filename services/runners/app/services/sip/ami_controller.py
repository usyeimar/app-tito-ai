"""Asterisk AMI (Manager Interface) controller using panoramisk.

Provides async control over Asterisk for:
- Monitoring call events (Newchannel, Hangup, DTMFEnd)
- Executing actions (Hangup, Originate, GetVar)
- Monitoring SIP registrations
"""

import asyncio
import logging
from typing import Optional, Callable, Awaitable, Dict, Any

from panoramisk import Manager

logger = logging.getLogger(__name__)

# Type aliases
EventCallback = Callable[[Dict[str, Any]], Awaitable[None]]


class AMIController:
    """Async controller for Asterisk Manager Interface.

    Usage:
        ami = AMIController(host="localhost", port=5038, username="tito", secret="secret")
        ami.on_dtmf = my_dtmf_handler
        ami.on_hangup = my_hangup_handler
        await ami.connect()
        ...
        await ami.disconnect()
    """

    def __init__(
        self,
        host: str = "localhost",
        port: int = 5038,
        username: str = "tito",
        secret: str = "",
    ):
        self._host = host
        self._port = port
        self._username = username
        self._secret = secret
        self._manager: Optional[Manager] = None
        self._connected = False

        # Event callbacks (set by call_handler)
        self.on_new_channel: Optional[EventCallback] = None
        self.on_hangup: Optional[EventCallback] = None
        self.on_dtmf: Optional[EventCallback] = None
        self.on_registry: Optional[EventCallback] = None

    @property
    def connected(self) -> bool:
        return self._connected

    async def connect(self):
        """Connect to Asterisk AMI."""
        self._manager = Manager(
            host=self._host,
            port=self._port,
            username=self._username,
            secret=self._secret,
        )

        # Register event handlers
        self._manager.register_event("Newchannel", self._handle_new_channel)
        self._manager.register_event("Hangup", self._handle_hangup)
        self._manager.register_event("DTMFEnd", self._handle_dtmf)
        self._manager.register_event("Registry", self._handle_registry)

        await self._manager.connect()
        self._connected = True
        logger.info(f"AMI connected to {self._host}:{self._port}")

    async def disconnect(self):
        """Disconnect from Asterisk AMI."""
        if self._manager:
            self._manager.close()
            self._connected = False
            logger.info("AMI disconnected")

    # ── Actions ───────────────────────────────────────────────────────────────

    async def hangup(self, channel: str, cause: int = 16):
        """Hang up a channel.

        Args:
            channel: The Asterisk channel name (e.g., "PJSIP/trunk-00000001")
            cause: Hangup cause code (16 = Normal Clearing)
        """
        if not self._manager:
            return
        response = await self._manager.send_action({
            "Action": "Hangup",
            "Channel": channel,
            "Cause": str(cause),
        })
        logger.info(f"Hangup channel={channel} cause={cause} response={response}")
        return response

    async def originate(
        self,
        channel: str,
        context: str = "tito-outbound",
        exten: str = "s",
        priority: str = "1",
        caller_id: Optional[str] = None,
        variables: Optional[Dict[str, str]] = None,
        timeout: int = 30000,
    ):
        """Originate an outbound call.

        Args:
            channel: Dial string (e.g., "PJSIP/+573001234567@twilio-trunk")
            context: Dialplan context
            exten: Dialplan extension
            priority: Dialplan priority
            caller_id: Caller ID to set
            variables: Channel variables to set (e.g., CALL_ID, AGENT_ID)
            timeout: Timeout in milliseconds
        """
        if not self._manager:
            return

        action: Dict[str, str] = {
            "Action": "Originate",
            "Channel": channel,
            "Context": context,
            "Exten": exten,
            "Priority": priority,
            "Timeout": str(timeout),
            "Async": "true",
        }

        if caller_id:
            action["CallerID"] = caller_id

        if variables:
            var_str = ",".join(f"{k}={v}" for k, v in variables.items())
            action["Variable"] = var_str

        response = await self._manager.send_action(action)
        logger.info(f"Originate channel={channel} response={response}")
        return response

    async def get_variable(self, channel: str, variable: str) -> Optional[str]:
        """Get a channel variable value."""
        if not self._manager:
            return None
        response = await self._manager.send_action({
            "Action": "Getvar",
            "Channel": channel,
            "Variable": variable,
        })
        return response.get("Value")

    async def get_sip_registrations(self) -> list:
        """Get list of active PJSIP registrations."""
        if not self._manager:
            return []
        response = await self._manager.send_action({
            "Action": "PJSIPShowRegistrationsOutbound",
        })
        return response if isinstance(response, list) else [response]

    # ── Event Handlers ────────────────────────────────────────────────────────

    async def _handle_new_channel(self, manager, event):
        """Handle Newchannel event from Asterisk."""
        event_data = {
            "channel": event.get("Channel", ""),
            "uniqueid": event.get("Uniqueid", ""),
            "caller_id_num": event.get("CallerIDNum", ""),
            "caller_id_name": event.get("CallerIDName", ""),
            "exten": event.get("Exten", ""),
            "context": event.get("Context", ""),
        }
        logger.debug(f"AMI Newchannel: {event_data}")
        if self.on_new_channel:
            await self.on_new_channel(event_data)

    async def _handle_hangup(self, manager, event):
        """Handle Hangup event from Asterisk."""
        event_data = {
            "channel": event.get("Channel", ""),
            "uniqueid": event.get("Uniqueid", ""),
            "cause": event.get("Cause", ""),
            "cause_txt": event.get("Cause-txt", ""),
        }
        logger.info(f"AMI Hangup: channel={event_data['channel']} cause={event_data['cause_txt']}")
        if self.on_hangup:
            await self.on_hangup(event_data)

    async def _handle_dtmf(self, manager, event):
        """Handle DTMFEnd event from Asterisk."""
        event_data = {
            "channel": event.get("Channel", ""),
            "uniqueid": event.get("Uniqueid", ""),
            "digit": event.get("Digit", ""),
            "direction": event.get("Direction", ""),
        }
        logger.debug(f"AMI DTMF: digit={event_data['digit']} channel={event_data['channel']}")
        if self.on_dtmf:
            await self.on_dtmf(event_data)

    async def _handle_registry(self, manager, event):
        """Handle Registry event (SIP registration status changes)."""
        event_data = {
            "channel_type": event.get("ChannelType", ""),
            "username": event.get("Username", ""),
            "domain": event.get("Domain", ""),
            "status": event.get("Status", ""),
        }
        logger.info(f"AMI Registry: {event_data['username']}@{event_data['domain']} → {event_data['status']}")
        if self.on_registry:
            await self.on_registry(event_data)
