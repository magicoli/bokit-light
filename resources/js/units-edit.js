/**
 * Unit Edit Form - Alpine.js Component
 */
window.unitForm = function () {
    return {
        manuallyEdited: false,
        sources: [],

        addSource() {
            this.sources.push({
                id: null,
                type: "ical",
                url: "",
                last_sync_at: null,
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
                    .normalize("NFD")
                    .replace(/[\u0300-\u036f]/g, "")
                    .replace(/[^a-z0-9\s-]/g, "")
                    .replace(/\s+/g, "-")
                    .replace(/-+/g, "-")
                    .replace(/^-|-$/g, "");

                this.$refs.slugInput.value = slug;
            }
        },
    };
};

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("unit-edit-form");
    if (!form) return;

    // Load data from data attributes
    let sources = [];
    if (form.dataset.sources) {
        try {
            sources = JSON.parse(form.dataset.sources);
        } catch (e) {
            console.error("Error parsing sources data:", e);
        }
    }

    // Create component with loaded data
    const component = window.unitForm();
    component.sources = sources.length > 0 ? sources : [];
    
    // Initialize Alpine on the form with the data
    Alpine.data("unitFormWithData", function () {
        return component;
    });

    // Apply Alpine data to form
    form.setAttribute("x-data", "unitFormWithData()");
    Alpine.initTree(form);
});
