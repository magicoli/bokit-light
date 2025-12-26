// Date autofill fix
//

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-alpine-date]").forEach((input) => {
        // Ajouter la classe 'relative' si elle n'est pas déjà présente
        if (!input.classList.contains("relative")) {
            input.classList.add("relative");
        }

        let touched = false;

        // Focus -> type=date
        input.addEventListener("focus", () => {
            input.type = "date";
        });

        // Blur -> repasser en text si vide
        input.addEventListener("blur", () => {
            if (!input.value) {
                input.type = "text";
                touched = false;
            } else {
                touched = true;
            }
        });

        // Marquer comme touché dès que l'utilisateur change la valeur
        input.addEventListener("input", () => {
            touched = true;
        });

        input.addEventListener("change", () => {
            touched = true;
        });
    });

    // Date range min validation and auto-focus
    document.querySelectorAll(".date-range, .field-date-range .date-range").forEach((container) => {
        const inputs = container.querySelectorAll('input[type="date"]');
        if (inputs.length === 2) {
            const [fromInput, toInput] = inputs;
            
            // Update min on to field when from changes
            fromInput.addEventListener("change", () => {
                if (fromInput.value) {
                    toInput.min = fromInput.value;
                    
                    // Clear to value if it's before the new from value
                    if (toInput.value && toInput.value < fromInput.value) {
                        toInput.value = "";
                    }
                    
                    // Auto-focus to field after selecting from
                    toInput.focus();
                } else {
                    toInput.removeAttribute("min");
                }
            });
            
            // Initialize min if from already has a value
            if (fromInput.value) {
                toInput.min = fromInput.value;
            }
        }
    });
});
