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
});

