// Alpine.js is included automatically by Livewire Flux via @fluxScripts
// No manual initialization needed for TALL stack with Livewire

window.notesCountdown = function notesCountdown(initial = {}) {
    return {
        timezone: initial.timezone || 'UTC',
        now: initial.nowIso ? new Date(initial.nowIso) : new Date(),
        target: initial.nextIso ? new Date(initial.nextIso) : null,
        offset: 0,
        intervalId: null,
        eventListener: null,
        currentDisplay: '',
        countdownDisplay: '',
        nextScheduledDisplay: '',
        hasTriggeredSend: false,

        init() {
            this.syncFromServer(initial);
            this.eventListener = (event) => {
                const detail = event?.detail ?? {};
                this.syncFromServer(detail);
            };

            window.addEventListener('notes-countdown-sync', this.eventListener);
        },

        syncFromServer(detail = {}) {
            if (detail.timezone) {
                this.timezone = detail.timezone;
            }

            if (detail.nowIso) {
                const newNow = new Date(detail.nowIso);
                if (!Number.isNaN(newNow)) {
                    this.now = newNow;
                    this.offset = this.now.getTime() - Date.now();
                }
            } else if (!this.offset) {
                this.offset = this.now.getTime() - Date.now();
            }

            if (Object.prototype.hasOwnProperty.call(detail, 'nextIso')) {
                this.target = detail.nextIso ? new Date(detail.nextIso) : null;
                this.hasTriggeredSend = false;
            }

            this.restartInterval();
        },

        restartInterval() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
            }

            this.updateDisplays();
            this.intervalId = setInterval(() => {
                this.now = new Date(Date.now() + this.offset);
                this.updateDisplays();
            }, 1000);
        },

        updateDisplays() {
            const tz = this.timezone || 'UTC';
            const options = {
                timeZone: tz,
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            };

            this.currentDisplay = this.now.toLocaleString(undefined, options);

            if (this.target) {
                const targetDisplay = this.target.toLocaleString(undefined, options);
                const diff = this.target.getTime() - this.now.getTime();

                if (diff > 0) {
                    const totalSeconds = Math.floor(diff / 1000);
                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    this.countdownDisplay = `${days}d ${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
                    this.nextScheduledDisplay = `Scheduled for ${targetDisplay}`;
                    this.hasTriggeredSend = false;
                } else {
                    this.countdownDisplay = '';
                    this.nextScheduledDisplay = '';
                    this.hasTriggeredSend = true;
                }
            } else {
                this.countdownDisplay = '';
                this.nextScheduledDisplay = '';
            }
        },

        destroy() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
            }

            if (this.eventListener) {
                window.removeEventListener('notes-countdown-sync', this.eventListener);
            }
        },
    };
};

