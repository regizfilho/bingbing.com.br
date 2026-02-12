const shareProfile = () => ({
    isVisible: false,

    init() {
        if (navigator.share) {
            this.isVisible = true;
        }
    },

    share(options) {
        if (navigator.share) {
            navigator.share({
                title: options.title || 'Bingo',
                text: options.text || options.message,
                url: options.url
            }).catch(() => {
                this.fallbackShare(options);
            });
        } else {
            this.fallbackShare(options);
        }
    },

    fallbackShare(options) {
        const url = options.url;
        const text = encodeURIComponent(options.text || options.message || '');
        
        const whatsappUrl = `https://api.whatsapp.com/send?text=${text}%20${encodeURIComponent(url)}`;
        const twitterUrl = `https://twitter.com/intent/tweet?text=${text}&url=${encodeURIComponent(url)}`;
        
        window.open(whatsappUrl, '_blank');
    },

    twitter(options) {
        const text = options.question ? options.question + '%0A%0A' : '';
        const message = encodeURIComponent(options.message || '');
        const url = encodeURIComponent(options.url);
        
        window.open(`https://twitter.com/intent/tweet?text=${text}${message}&url=${url}`, '_blank');
    }
});

export { shareProfile };