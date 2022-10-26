// Error handling class
// Usable in all components
export default class Errors {
    constructor() {
        this.errors = {}
    }

    get(field) {
        if (this.errors[field]) {
            return this.errors[field]
        }
    }

    first(field) {
        if (this.errors[field]) {
            if (Array.isArray(this.errors[field])) {
                let keys = Object.keys(this.errors[field]);
                return keys.length ? this.errors[field][keys[0]] : '';
            } else {
                return this.errors[field];
            }
        }
    }

    has(field) {
        return !! this.errors[field]
    }

    record(errors) {
        this.errors = errors
    }

    clear(field) {
        if (field) {
            this.errors[field] = null
        } else {
            this.errors = {}
        }
    }
}