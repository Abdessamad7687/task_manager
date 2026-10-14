import Sortable from "sortablejs";

const projectFilter = document.querySelector("[data-project-filter]");
projectFilter?.addEventListener("change", () =>
    projectFilter.form.requestSubmit(),
);

document.querySelectorAll("[data-delete-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (!window.confirm("Delete this task? This cannot be undone.")) {
            event.preventDefault();
        }
    });
});

const editForm = document.querySelector("#edit-task-form");
if (editForm) {
    const project = document.querySelector("#edit-project");
    const priority = document.querySelector("#edit-priority");
    const updatePriority = (changed) => {
        priority.max = project.selectedOptions[0].dataset.max;
        if (changed) {
            priority.value =
                project.value === editForm.dataset.originalProject
                    ? editForm.dataset.originalPriority
                    : priority.max;
        }
    };
    updatePriority(false);
    project.addEventListener("change", () => updatePriority(true));
}

const list = document.querySelector("#task-list");
if (list && list.children.length) {
    const status = document.querySelector("#save-status");
    const error = document.querySelector("#reorder-error");
    let busy = false;
    let blocked = false;
    let beforeDrag = [];
    const rows = () => Array.from(list.children);
    const refreshLabels = () => {
        rows().forEach((row, index, all) => {
            const badge = row.querySelector(".priority-badge");
            badge.textContent = `#${index + 1}`;
            badge.setAttribute("aria-label", `Priority ${index + 1}`);
            row.querySelector('[data-move="up"]').disabled =
                busy || blocked || index === 0;
            row.querySelector('[data-move="down"]').disabled =
                busy || blocked || index === all.length - 1;
        });
    };
    const save = async (previousOrder) => {
        busy = true;
        sortable.option("disabled", true);
        list.setAttribute("aria-busy", "true");
        refreshLabels();
        status.textContent = "Saving order…";
        error.hidden = true;
        try {
            const response = await fetch(list.dataset.reorderUrl, {
                method: "PATCH",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    ).content,
                },
                body: JSON.stringify({
                    project_id: Number(list.dataset.projectId),
                    task_ids: rows().map((row) => Number(row.dataset.id)),
                    version: list.dataset.version,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(
                    response.status === 419
                        ? "Your session expired. Reload tasks and try again."
                        : data.message ||
                              "The order could not be saved. Reload tasks and try again.",
                );
            }
            list.dataset.version = data.version;
            status.textContent = "Order saved";
        } catch (failure) {
            previousOrder.forEach((row) => list.append(row));
            status.textContent = "Order not saved";
            error.querySelector("span").textContent =
                failure.message ||
                "Connection lost. Reload tasks to check the saved order.";
            error.hidden = false;
            // A network failure may happen after the server committed. Require
            // a reload rather than retrying with uncertain local state.
            blocked = true;
        } finally {
            busy = false;
            list.setAttribute("aria-busy", "false");
            sortable.option("disabled", blocked);
            refreshLabels();
        }
    };
    const sortable = Sortable.create(list, {
        draggable: ".task-row",
        filter: "a, button, input, select, textarea, form",
        preventOnFilter: false,
        animation: window.matchMedia("(prefers-reduced-motion: reduce)").matches
            ? 0
            : 150,
        ghostClass: "opacity-25",
        onStart: () => {
            beforeDrag = rows();
        },
        onEnd: (event) => {
            if (event.oldIndex !== event.newIndex) {
                save(beforeDrag);
            }
        },
    });
    document.querySelectorAll("[data-move-actions]").forEach((actions) => {
        actions.hidden = false;
    });
    list.addEventListener("click", (event) => {
        const button = event.target.closest("[data-move]");
        if (!button || busy || blocked) {
            return;
        }
        const row = button.closest(".task-row");
        const previousOrder = rows();
        if (button.dataset.move === "up" && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.dataset.move === "down" && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        } else {
            return;
        }
        save(previousOrder).then(() => {
            if (button.disabled) {
                row.querySelector(
                    `[data-move="${button.dataset.move === "up" ? "down" : "up"}"]`,
                ).focus();
            } else {
                button.focus();
            }
        });
    });
    refreshLabels();
}
