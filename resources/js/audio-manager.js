class AudioManager {
    constructor() {
        this.audioEnabled = true;
        this.ttsEnabled = true;
        this.currentAudio = null;
        this.audioCache = new Map(); // Cache para melhorar performance
        this.init();
    }

    init() {
        window.addEventListener('livewire:init', () => {
            Livewire.on('audio-toggle', (data) => {
                this.audioEnabled = data.enabled;
                if (!this.audioEnabled) this.stopAll();
            });

            Livewire.on('tts-toggle', (data) => {
                this.ttsEnabled = data.enabled;
                if (!this.ttsEnabled) speechSynthesis.cancel();
            });

            Livewire.on('change-sound', (data) => {
                localStorage.setItem(`sound_${data.sound}`, data.name);
            });

            Livewire.on('play-sound', (data) => {
                this.play(data.type, data.name);
            });
        });

        this.loadVoices();
    }

    loadVoices() {
        speechSynthesis.onvoiceschanged = () => {
            console.log('Vozes carregadas:', speechSynthesis.getVoices().map(v => v.name));
        };
    }

    async play(type, name) {
        if (!this.audioEnabled) return;

        try {
            const audio = await this.getAudio(name);
            if (audio) {
                this.playAudio(audio);
            }
        } catch (error) {
            console.warn('Erro ao tocar som:', error);
        }
    }

    async getAudio(name) {
        if (this.audioCache.has(name)) {
            return this.audioCache.get(name);
        }

        const response = await fetch(`/api/game-audio/${name}`);
        const data = await response.json();

        let audio;

        if (data.audio_type === 'mp3') {
            audio = new Audio(`/storage/${data.file_path}`);
        } else if (data.audio_type === 'tts' && this.ttsEnabled) {
            const utterance = new SpeechSynthesisUtterance(data.tts_text);
            utterance.lang = data.tts_language || 'pt-BR';
            utterance.rate = data.tts_rate || 1.1;
            utterance.pitch = data.tts_pitch || 1.0;
            utterance.volume = data.tts_volume || 0.9;

            // Tenta usar a voz preferida
            const voices = speechSynthesis.getVoices();
            utterance.voice = voices.find(v => 
                v.name.includes(data.tts_voice) || 
                v.lang === data.tts_language
            ) || voices[0];

            audio = utterance;
        }

        if (audio) this.audioCache.set(name, audio);
        return audio;
    }

    playAudio(audio) {
        // Para som anterior
        if (this.currentAudio) {
            if (this.currentAudio instanceof Audio) {
                this.currentAudio.pause();
            } else {
                speechSynthesis.cancel();
            }
        }

        this.currentAudio = audio;

        if (audio instanceof SpeechSynthesisUtterance) {
            speechSynthesis.speak(audio);
        } else if (audio instanceof Audio) {
            audio.play().catch(err => console.warn('MP3 play failed:', err));
            audio.onended = () => { this.currentAudio = null; };
        }
    }

    stopAll() {
        if (this.currentAudio instanceof Audio) {
            this.currentAudio.pause();
        } else {
            speechSynthesis.cancel();
        }
        this.currentAudio = null;
    }
}

window.audioManager = new AudioManager();