import React from 'react';
import { Mic, MicOff, Phone, PhoneOff, User, Bot, Headphones } from 'lucide-react';
import {
    Room,
    RoomEvent,
    Track,
    type LocalAudioTrack,
    type RemoteTrack,
    type RemoteTrackPublication,
    type RemoteParticipant,
} from 'livekit-client';
import DailyIframe, {
    type DailyCall,
    type DailyEventObject,
} from '@daily-co/daily-js';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TenantApiError, webGet, webPost, webDelete } from '@/lib/tenant-api';
import { cn } from '@/lib/utils';

type CallStatus = 'idle' | 'connecting' | 'connected' | 'ending' | 'error' | 'post-call';

type TranscriptEntry = {
    role: 'user' | 'agent' | 'assistant';
    content: string;
    timestamp?: number;
};

type SessionResponse = {
    data: {
        session_id: string;
        room_name: string;
        provider: string;
        url: string;
        access_token: string;
        runner_ws_url: string;
    };
};

type SessionStatus = {
    status: 'active' | 'ended';
    ended_by?: 'agent' | 'user';
    ended_at?: string;
    data?: Record<string, unknown>;
};

type Props = {
    tenantSlug: string;
    agentId: string;
    agentName: string;
    variables?: { name: string; value: string }[];
};

const POLL_INTERVAL = 1000;

export function AgentTestCall({ tenantSlug, agentId, agentName, variables }: Props) {
    const [status, setStatus] = React.useState<CallStatus>('idle');
    const [muted, setMuted] = React.useState(false);
    const [errorMsg, setErrorMsg] = React.useState<string | null>(null);
    const [elapsed, setElapsed] = React.useState(0);
    const [provider, setProvider] = React.useState<string | null>(null);
    const [postCallData, setPostCallData] = React.useState<Record<string, unknown> | null>(null);
    const [transcripts, setTranscripts] = React.useState<TranscriptEntry[]>([]);

    const roomRef = React.useRef<Room | null>(null);
    const dailyRef = React.useRef<DailyCall | null>(null);
    const sessionIdRef = React.useRef<string | null>(null);
    const audioElRef = React.useRef<HTMLAudioElement | null>(null);
    const timerRef = React.useRef<number | null>(null);
    const pollRef = React.useRef<number | null>(null);
    const transcriptWsRef = React.useRef<WebSocket | null>(null);
    const transcriptsEndRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        return () => {
            stopTimer();
            stopPolling();
            disconnectTranscriptWebSocket();
            void roomRef.current?.disconnect();
            roomRef.current = null;
            dailyRef.current?.destroy();
            dailyRef.current = null;
            void terminateRunnerSession();
        };
    }, []);

    // Auto-scroll transcripts
    React.useEffect(() => {
        transcriptsEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [transcripts]);

    const startTimer = () => {
        const startedAt = Date.now();
        timerRef.current = window.setInterval(() => {
            setElapsed(Math.floor((Date.now() - startedAt) / 1000));
        }, 500);
    };

    const stopTimer = () => {
        if (timerRef.current !== null) {
            window.clearInterval(timerRef.current);
            timerRef.current = null;
        }
        setElapsed(0);
    };

    const startPolling = React.useCallback(() => {
        stopPolling();
        pollRef.current = window.setInterval(async () => {
            const sessionId = sessionIdRef.current;
            if (!sessionId) return;
            try {
                const response = await webGet<{ data: SessionStatus }>(
                    tenantSlug,
                    `/runner/sessions/${sessionId}/status`,
                );
                const sessionData = response.data;
                if (sessionData.status === 'ended' && sessionData.ended_by === 'agent') {
                    stopPolling();
                    stopTimer();
                    void roomRef.current?.disconnect();
                    roomRef.current = null;
                    dailyRef.current?.destroy();
                    dailyRef.current = null;
                    setPostCallData(sessionData.data || {});
                    setStatus('post-call');
                }
            } catch { /* polling failure is non-fatal */ }
        }, POLL_INTERVAL);
    }, [tenantSlug]);

    const stopPolling = () => {
        if (pollRef.current !== null) {
            window.clearInterval(pollRef.current);
            pollRef.current = null;
        }
    };

    const connectTranscriptWebSocket = React.useCallback((sessionId: string, runnerWsUrl: string) => {
        if (transcriptWsRef.current) transcriptWsRef.current.close();
        const wsUrl = runnerWsUrl.replace(/^http/, 'ws');
        const ws = new WebSocket(`${wsUrl}/api/v1/sessions/${sessionId}/transcript`);
        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'transcript') {
                    setTranscripts((prev) => [...prev, {
                        role: data.role === 'assistant' ? 'agent' : data.role,
                        content: data.content,
                        timestamp: data.timestamp,
                    }]);
                }
            } catch { /* ignore parse errors */ }
        };
        ws.onclose = () => { transcriptWsRef.current = null; };
        ws.onerror = () => { transcriptWsRef.current = null; };
        transcriptWsRef.current = ws;
    }, []);

    const disconnectTranscriptWebSocket = () => {
        if (transcriptWsRef.current) {
            transcriptWsRef.current.close();
            transcriptWsRef.current = null;
        }
    };

    const terminateRunnerSession = async () => {
        const sessionId = sessionIdRef.current;
        if (!sessionId) return;
        sessionIdRef.current = null;
        try { await webDelete(tenantSlug, `/agents/${agentId}/test-call/${sessionId}`); } catch { /* fire-and-forget */ }
    };

    const connectLivekit = async (url: string, token: string) => {
        const room = new Room({ adaptiveStream: true, dynacast: true });
        roomRef.current = room;
        room.on(RoomEvent.TrackSubscribed, (track: RemoteTrack) => {
            if (track.kind === Track.Kind.Audio && audioElRef.current) track.attach(audioElRef.current);
        });
        room.on(RoomEvent.TrackUnsubscribed, (track: RemoteTrack, _pub: RemoteTrackPublication, _p: RemoteParticipant) => { track.detach(); });
        room.on(RoomEvent.Disconnected, () => {
            if (status !== 'post-call') { stopTimer(); stopPolling(); setStatus('idle'); }
            void terminateRunnerSession();
        });
        await room.connect(url, token);
        await room.localParticipant.setMicrophoneEnabled(true);
    };

    const connectDaily = async (url: string, token: string) => {
        const call = DailyIframe.createCallObject();
        dailyRef.current = call;
        call.on('track-started', (ev: DailyEventObject) => {
            const track = ev.track as MediaStreamTrack | undefined;
            if (track && track.kind === 'audio' && audioElRef.current) {
                audioElRef.current.srcObject = new MediaStream([track]);
            }
        });
        call.on('left-meeting', () => {
            if (status !== 'post-call') { stopTimer(); stopPolling(); setStatus('idle'); }
            dailyRef.current = null;
            void terminateRunnerSession();
        });
        await call.join({ url, token, videoSource: false });
        await call.setLocalAudio(true);
    };

    const handleStart = async () => {
        setErrorMsg(null);
        setStatus('connecting');
        setPostCallData(null);
        try {
            const response = await webPost<SessionResponse>(tenantSlug, `/agents/${agentId}/test-call`, { variables });
            const session = response.data;
            sessionIdRef.current = session.session_id;
            setProvider(session.provider);
            if (!session.url || !session.access_token) throw new Error('La respuesta del runner no incluye url o access_token.');
            if (session.provider === 'livekit') await connectLivekit(session.url, session.access_token);
            else if (session.provider === 'daily') await connectDaily(session.url, session.access_token);
            else throw new Error(`Transport "${session.provider}" no soportado.`);
            setStatus('connected');
            startTimer();
            startPolling();
            setTranscripts([]);
            connectTranscriptWebSocket(session.session_id, session.runner_ws_url);
        } catch (err) {
            setErrorMsg(err instanceof TenantApiError ? err.message : err instanceof Error ? err.message : 'No se pudo iniciar la llamada');
            setStatus('error');
            stopPolling();
            void roomRef.current?.disconnect();
            roomRef.current = null;
            dailyRef.current?.destroy();
            dailyRef.current = null;
            await terminateRunnerSession();
            stopTimer();
        }
    };

    const handleHangup = async () => {
        setStatus('ending');
        stopPolling();
        disconnectTranscriptWebSocket();
        try {
            const sessionId = sessionIdRef.current;
            if (sessionId) await webPost(tenantSlug, `/runner/sessions/${sessionId}/user-ended`).catch(() => {});
            if (dailyRef.current) { await dailyRef.current.leave(); dailyRef.current.destroy(); dailyRef.current = null; }
            if (roomRef.current) await roomRef.current.disconnect();
        } finally {
            roomRef.current = null;
            await terminateRunnerSession();
            stopTimer();
            setStatus('idle');
            setMuted(false);
            setPostCallData(null);
        }
    };

    const handleToggleMute = async () => {
        const next = !muted;
        if (provider === 'daily' && dailyRef.current) {
            await dailyRef.current.setLocalAudio(!next);
            setMuted(next);
            return;
        }
        const room = roomRef.current;
        if (!room) return;
        const pub = room.localParticipant.getTrackPublications().find((p) => p.kind === Track.Kind.Audio);
        const track = pub?.track as LocalAudioTrack | undefined;
        if (track) { if (next) await track.mute(); else await track.unmute(); }
        setMuted(next);
    };

    const isLive = status === 'connected';
    const isBusy = status === 'connecting' || status === 'ending';

    return (
        <div className="flex flex-col overflow-hidden rounded-xl border border-border bg-card">
            <audio ref={audioElRef} autoPlay playsInline className="hidden" />

            {/* Header */}
            <div className="flex items-center gap-2 border-b border-border px-3 py-2.5">
                <Headphones className="size-4 text-muted-foreground" />
                <span className="flex-1 text-sm font-medium">Test via browser</span>
                <Badge variant="secondary" className="text-[10px]">BETA</Badge>
                {isLive && (
                    <span className="flex items-center gap-1.5 text-xs text-emerald-600">
                        <span className="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                        {formatElapsed(elapsed)}
                    </span>
                )}
            </div>

            {/* Transcript area */}
            <div className="flex flex-1 flex-col gap-1.5 overflow-y-auto p-3" style={{ maxHeight: '280px', minHeight: '120px' }}>
                {status === 'idle' && transcripts.length === 0 && (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center">
                        <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                            <Phone className="size-4 text-muted-foreground" />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Start a live call to test your agent
                        </p>
                    </div>
                )}

                {status === 'connecting' && (
                    <div className="flex flex-1 items-center justify-center">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="size-1.5 animate-pulse rounded-full bg-amber-500" />
                            Connecting to {agentName}...
                        </div>
                    </div>
                )}

                {(isLive || status === 'post-call' || (status === 'idle' && transcripts.length > 0)) && (
                    <>
                        {transcripts.length === 0 && isLive && (
                            <div className="flex flex-1 items-center justify-center">
                                <p className="text-xs italic text-muted-foreground">Waiting for transcript...</p>
                            </div>
                        )}
                        {transcripts.map((entry, idx) => (
                            <div
                                key={idx}
                                className={cn(
                                    'flex max-w-[85%] gap-1.5 rounded-lg px-2.5 py-1.5 text-xs leading-relaxed',
                                    entry.role === 'agent'
                                        ? 'self-start bg-muted'
                                        : 'self-end bg-primary text-primary-foreground',
                                )}
                            >
                                {entry.role === 'agent' && <Bot className="mt-0.5 size-3 shrink-0 text-muted-foreground" />}
                                <span>{entry.content}</span>
                                {entry.role !== 'agent' && <User className="mt-0.5 size-3 shrink-0 opacity-70" />}
                            </div>
                        ))}
                        <div ref={transcriptsEndRef} />
                    </>
                )}

                {status === 'error' && errorMsg && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                        {errorMsg}
                    </div>
                )}

                {status === 'post-call' && (
                    <div className="mt-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300">
                        Call ended by agent.
                        {postCallData && Object.keys(postCallData).length > 0 && ' Analytics data available.'}
                    </div>
                )}
            </div>

            {/* Controls */}
            <div className="flex items-center gap-2 border-t border-border px-3 py-2.5">
                {status === 'post-call' ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full text-xs"
                        onClick={() => { setStatus('idle'); setPostCallData(null); sessionIdRef.current = null; }}
                    >
                        New call
                    </Button>
                ) : !isLive ? (
                    <Button
                        size="sm"
                        className="w-full gap-1.5 text-xs"
                        onClick={handleStart}
                        disabled={isBusy}
                    >
                        <Phone className="size-3.5" />
                        {status === 'connecting' ? 'Connecting...' : 'Start call'}
                    </Button>
                ) : (
                    <>
                        <Button
                            variant="destructive"
                            size="sm"
                            className="flex-1 gap-1.5 text-xs"
                            onClick={handleHangup}
                            disabled={isBusy}
                        >
                            <PhoneOff className="size-3.5" />
                            Hang up
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={handleToggleMute}
                        >
                            {muted ? <MicOff className="size-3.5" /> : <Mic className="size-3.5" />}
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function formatElapsed(seconds: number): string {
    const mm = Math.floor(seconds / 60).toString().padStart(2, '0');
    const ss = (seconds % 60).toString().padStart(2, '0');
    return `${mm}:${ss}`;
}
