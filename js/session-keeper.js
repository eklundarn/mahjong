/**
 * Session Keeper - håller sessionen vid liv vid aktivitet
 * och skickar periodisk heartbeat som säkerhetsnät.
 * Varnar användaren när sessionen är nära att gå ut.
 */

(function() {
    const API_ENDPOINT = '/api/session_heartbeat.php';
    const THROTTLE_TIME    = 30000;  // Max en heartbeat per 30 sek vid aktivitet
    const PERIODIC_INTERVAL = 240000; // Säkerhetsnät: heartbeat var 4:e minut oavsett aktivitet
    const WARN_THRESHOLD   = 300;    // Varna när < 5 minuter kvar

    let lastHeartbeatSent = 0;
    let activityTimeout   = null;
    let periodicTimer     = null;
    let warnShown         = false;
    let warnBanner        = null;

    async function sendHeartbeat() {
        const now = Date.now();
        if (now - lastHeartbeatSent < THROTTLE_TIME) return;
        lastHeartbeatSent = now;

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ heartbeat: true, timestamp: now })
            });

            const data = await response.json();

            if (!data.session_active) {
                handleSessionExpired();
            } else {
                hideWarnBanner();
                warnShown = false;
                if (data.time_remaining !== undefined && data.time_remaining < WARN_THRESHOLD) {
                    showWarnBanner(data.time_remaining);
                }
            }
        } catch (error) {
            console.error('Heartbeat misslyckades:', error);
        }
    }

    function handleSessionExpired() {
        if (warnBanner) warnBanner.remove();
        if (typeof window.autosaveNow === 'function') window.autosaveNow();
        const msg = 'Din session har gått ut. Dina uppgifter är sparade lokalt – de återställs när du loggar in igen. Klicka OK för att logga in.';
        if (confirm(msg)) {
            window.location.href = '/statistik/login.php?session_expired=1';
        }
    }

    function showWarnBanner(secondsLeft) {
        if (warnShown) return;
        warnShown = true;
        warnBanner = document.createElement('div');
        warnBanner.id = 'session-warn-banner';
        warnBanner.style.cssText = [
            'position:fixed','bottom:0','left:0','right:0','z-index:9999',
            'background:#e65100','color:white','text-align:center',
            'padding:12px 20px','font-size:14px','font-weight:500',
            'box-shadow:0 -2px 8px rgba(0,0,0,0.3)'
        ].join(';');
        const mins = Math.ceil(secondsLeft / 60);
        warnBanner.innerHTML = `⚠️ Sessionen går ut om ca ${mins} minut${mins !== 1 ? 'er' : ''} – <strong>klicka var som helst på sidan för att förlänga</strong>`;
        document.body.appendChild(warnBanner);
    }

    function hideWarnBanner() {
        if (warnBanner) { warnBanner.remove(); warnBanner = null; }
    }

    function registerActivity() {
        if (activityTimeout) clearTimeout(activityTimeout);
        activityTimeout = setTimeout(sendHeartbeat, 1000);
    }

    function startPeriodicHeartbeat() {
        periodicTimer = setInterval(sendHeartbeat, PERIODIC_INTERVAL);
    }

    function startSessionKeeper() {
        const events = ['mousedown','keydown','click','input','change','touchstart','scroll'];
        events.forEach(e => document.addEventListener(e, registerActivity, { passive: true }));
        startPeriodicHeartbeat();
        console.log('Session keeper startad – förlängs vid aktivitet + var 4:e minut automatiskt');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startSessionKeeper);
    } else {
        startSessionKeeper();
    }
})();
