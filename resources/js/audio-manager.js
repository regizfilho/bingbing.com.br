// NO audio-manager.js, SUBSTITUA a classe AudioManager por:

class AudioManager {
    constructor() {
        this.audioEnabled = true;
        this.ttsEnabled = true;
        this.currentAudio = null;
        this.audioCache = new Map();
        this.voices = [];
        this.userInteracted = false; // NOVO
        this.init();
    }

    init() {
        // Registrar primeira interação do usuário
        const enableAudio = () => {
            this.userInteracted = true;
            console.log("✅ Usuário interagiu - áudio liberado");
            document.removeEventListener("click", enableAudio);
            document.removeEventListener("touchstart", enableAudio);
            document.removeEventListener("keydown", enableAudio);
        };

        document.addEventListener("click", enableAudio, { once: true });
        document.addEventListener("touchstart", enableAudio, { once: true });
        document.addEventListener("keydown", enableAudio, { once: true });

        window.addEventListener("livewire:init", () => {
            Livewire.on("audio-toggle", (data) => {
                this.audioEnabled = data.enabled;
                if (!this.audioEnabled) this.stopAll();
            });

            Livewire.on("tts-toggle", (data) => {
                this.ttsEnabled = data.enabled;
                if (!this.ttsEnabled) speechSynthesis.cancel();
            });

            Livewire.on("change-sound", (data) => {
                localStorage.setItem(`sound_${data.sound}`, data.name);
                this.audioCache.delete(data.name); // ← importante!
                console.log(`Cache limpo para som: ${data.name}`);
            });

            Livewire.on("play-sound", (data) => {
                this.play(data.type, data.name);
            });
        });

        this.loadVoices();
    }

    loadVoices() {
        const loadVoicesList = () => {
            this.voices = speechSynthesis.getVoices();
            console.log(
                "Vozes disponíveis:",
                this.voices.map((v) => `${v.name} (${v.lang})`),
            );
        };

        loadVoicesList();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = loadVoicesList;
        }
    }

    async play(type, name) {
        if (window.isControlPanel === true) {
            console.log(
                `[Controle] Evento ignorado localmente: ${type} - ${name}`,
            );
            return;
        }

        if (!this.audioEnabled) return;

        if (!this.userInteracted) {
            console.warn("Aguardando interação do usuário");
            return;
        }

        try {
            const audio = await this.getAudio(name);
            if (audio) {
                this.playAudio(audio);
            }
        } catch (error) {
            console.warn("Erro ao tocar som:", error);
        }
    }

    async getAudio(name) {
        if (this.audioCache.has(name)) {
            console.log(`Usando cache para: ${name}`);
            return this.audioCache.get(name);
        }

        console.log(`Buscando novo áudio: ${name}`);

        const response = await fetch(
            `/api/game-audio/${encodeURIComponent(name)}`,
        );
        const data = await response.json();

        let audio;

        if (data.audio_type === "mp3") {
            audio = new Audio(`/storage/${data.file_path}`);
            audio.preload = "auto";
        } else if (data.audio_type === "tts" && this.ttsEnabled) {
            const utterance = new SpeechSynthesisUtterance(
                data.tts_text || "Número sorteado",
            );
            utterance.lang = data.tts_language || "pt-BR";
            utterance.rate = data.tts_rate || 1.0;
            utterance.pitch = data.tts_pitch || 1.0;
            utterance.volume = data.tts_volume || 0.9;

            if (data.tts_voice) {
                let voice = this.voices.find(
                    (v) =>
                        v.name === data.tts_voice ||
                        v.name
                            .toLowerCase()
                            .includes(
                                data.tts_voice
                                    .toLowerCase()
                                    .replace("google ", ""),
                            ) ||
                        (v.lang === data.tts_language && v.default),
                );

                if (!voice) {
                    voice = this.voices.find(
                        (v) => v.lang === data.tts_language,
                    );
                }

                if (voice) {
                    utterance.voice = voice;
                    console.log(
                        `🎤 Voz selecionada: ${voice.name} (${voice.lang})`,
                    );
                } else {
                    console.warn(
                        `Nenhuma voz encontrada para: ${data.tts_voice}`,
                    );
                }
            }

            audio = utterance;
        }

        if (audio) this.audioCache.set(name, audio);
        return audio;
    }

    playAudio(audio) {
        if (this.currentAudio) {
            if (this.currentAudio instanceof Audio) {
                this.currentAudio.pause();
                this.currentAudio.currentTime = 0;
            } else {
                speechSynthesis.cancel();
            }
        }

        this.currentAudio = audio;

        if (audio instanceof SpeechSynthesisUtterance) {
            speechSynthesis.speak(audio);
            console.log("🎤 Tocando TTS");
        } else if (audio instanceof Audio) {
            audio
                .play()
                .then(() => console.log("🔊 MP3 tocando"))
                .catch((err) => console.warn("MP3 play failed:", err));
            audio.onended = () => {
                this.currentAudio = null;
            };
        }
    }

    stopAll() {
        if (this.currentAudio instanceof Audio) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
        } else {
            speechSynthesis.cancel();
        }
        this.currentAudio = null;
    }

    clearCache() {
        this.audioCache.clear();
    }
}

window.audioManager = new AudioManager();
console.log("=== AudioManager criado ===", window.audioManager);
