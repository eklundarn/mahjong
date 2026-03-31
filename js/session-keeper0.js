/**
 * Session Keeper - JavaScript för att hålla sessionen vid liv VID AKTIVITET
 * 
 * Lägg till detta script i ALLA sidor där användaren kan vara aktiv länge,
 * speciellt newgame.php där matcher pågår.
 * 
 * Funktionalitet:
 * - Sessionen förlängs BARA när användaren är aktiv (klickar, skriver, etc.)
 * - INGEN automatisk förnyelse var 5:e minut
 * - Efter 20 min total inaktivitet loggas användaren ut
 */

(function() {
    // Konfiguration
    const API_ENDPOINT = '/statistik/api/session_heartbeat.php'; // Ändra om din path är annorlunda
    const THROTTLE_TIME = 30000; // Skicka max var 30:e sekund (även vid mycket aktivitet)
    
    let lastHeartbeatSent = 0;
    let activityTimeout = null;
    
    /**
     * Skicka heartbeat till servern
     */
    async function sendHeartbeat() {
        const now = Date.now();
        
        // Throttle: Skicka inte oftare än var 30:e sekund
        if (now - lastHeartbeatSent < THROTTLE_TIME) {
            return;
        }
        
        lastHeartbeatSent = now;
        
        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    heartbeat: true,
                    timestamp: now
                })
            });
            
            const data = await response.json();
            
            if (!data.session_active) {
                // Sessionen är inte längre aktiv
                handleSessionExpired();
            } else {
                console.log('Session förlängd - tid kvar:', Math.floor(data.time_remaining / 60), 'minuter');
            }
            
        } catch (error) {
            console.error('Heartbeat misslyckades:', error);
        }
    }
    
    /**
     * Hantera utgången session
     */
    function handleSessionExpired() {
        // Visa varning
        if (confirm('Din session har gått ut på grund av inaktivitet. Klicka OK för att logga in igen.')) {
            window.location.href = '/statistik/login.php?session_expired=1';
        }
    }
    
    /**
     * Registrera användaraktivitet
     */
    function registerActivity() {
        // Debounce: Samla ihop aktivitet och skicka efter en kort paus
        if (activityTimeout) {
            clearTimeout(activityTimeout);
        }
        
        activityTimeout = setTimeout(() => {
            sendHeartbeat();
        }, 1000); // Vänta 1 sekund efter senaste aktivitet innan heartbeat skickas
    }
    
    /**
     * Starta session keeper
     */
    function startSessionKeeper() {
        // Lyssna på alla typer av användaraktivitet
        const activityEvents = [
            'mousedown',   // Musklick
            'keydown',     // Tangentbordstryck
            'click',       // Klick (även på knappar)
            'input',       // Text input, ändring i textfält
            'change',      // Ändring i dropdown, checkbox, radio
            'touchstart',  // Touch på mobil/surfplatta
            'scroll'       // Scrollning (för att hantera långa matcher)
        ];
        
        activityEvents.forEach(eventType => {
            document.addEventListener(eventType, registerActivity, { passive: true });
        });
        
        console.log('Session keeper startad - sessionen förlängs vid aktivitet');
    }
    
    // Starta när sidan har laddats
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startSessionKeeper);
    } else {
        startSessionKeeper();
    }
})();
