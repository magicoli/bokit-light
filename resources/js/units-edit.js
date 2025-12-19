/**
 * Unit Edit Form - Alpine.js Component
 */
window.unitForm = function() {
    return {
        manuallyEdited: false,
        sources: window.unitSourcesData || [],
        
        init() {
            if (this.sources.length === 0) {
                this.addSource();
            }
        },
        
        addSource() {
            this.sources.push({
                id: null,
                type: 'ical',
                url: '',
                last_sync_at: null
            });
        },
        
        removeSource(index) {
            if (this.sources.length > 1) {
                this.sources.splice(index, 1);
            }
        },
        
        updateSlug() {
            if (!this.manuallyEdited) {
                const name = this.$refs.nameInput.value;
                const slug = name
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                
                this.$refs.slugInput.value = slug;
            }
        }
    }
}
