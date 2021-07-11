class JsonPHP {
    // -------------------------------------------------------------------------
    // スタティック用
    // -------------------------------------------------------------------------

    static token;
    static url;

    static init(options) {
        if (typeof options === 'object') {
            if (options.url) {
                JsonPHP.url = options.url;
            }
        }
        return JsonPHP;
    }

    static make_fd(obj) {
        const data = new FormData;
        Object.keys(obj).forEach(el => {
            data.append(el, obj[el]);
        });
        return data;
    }

    static setToken(token) {
        JsonPHP.token = token;
        return JsonPHP;
    }

    static table(table) {
        return new JsonPHP(table);
    }

    static async migrate() {
        const res = await fetch(JsonPHP.url + "?cmd=migrate", {
            method: "GET"
        });
        const ret = await res.json();
        if (ret.result) {
            return true;
        }
        else {
            throw ret.message;
        }
    }

    static async auth(username, password) {
        const params = {};
        params.body = JsonPHP.make_fd({ username, password });
        params.method = "POST";

        const res = await fetch(JsonPHP.url + "?cmd=auth", params);
        const ret = await res.json();

        if (ret.result) {
            JsonPHP.token = ret.data.token;
            return ret.data.token;
        }
        else {
            throw ret.message;
        }
    }

    static async hash(password) {
        const res = await fetch(JsonPHP.url + "?cmd=hash&password=" + encodeURIComponent(password), {
            method: "GET"
        });
        const ret = await res.json();

        if (ret.result) {
            return ret.data.hash;
        }
        else {
            throw ret.message;
        }
    }

    // -------------------------------------------------------------------------
    // インスタンス用
    // -------------------------------------------------------------------------

    table = null;

    constructor(table) {
        if (!JsonPHP.token) {
            throw 'ログインするかtokenをセットしてください';
        }
        this.table = table;
    }

    arrayToWhere(arr) {
        const ret = [];
        if (typeof arr === 'object' && arr.constructor.name !== 'Array') {
            return ret;
        }
        if (typeof arr[0] === 'object' && arr[0].constructor.name === 'Array') {
            arr.forEach(a => {
                ret.push(this.arrayToWhere(a));
            });
        }
        else {
            return {
                column: arr[0],
                cond:   arr[1],
                value:  arr[2]
            };
        }
        return ret;
    }

    async fetch(mode, params) {
        const res = await fetch(JsonPHP.url + "?cmd=" + mode + "&table=" + this.table, params);
        const ret = await res.json();

        if (ret.result) {
            return ret.data;
        }
        else {
            throw ret.message;
        }
    }

    async get(where) {
        const params = {};
        params.method = 'POST';

        const body = {
            token: JsonPHP.token
        };
        if (typeof where === 'object' && where.constructor.name === 'Array') {
            body.where = JSON.stringify(this.arrayToWhere(where));
        }
        params.body = JsonPHP.make_fd(body);

        return this.fetch("get", params);
    }

    async add(data) {
        const params = {};
        params.method = 'POST';

        const body = {
            token: JsonPHP.token
        };
        body.data = JSON.stringify(data);
        params.body = JsonPHP.make_fd(body);

        return this.fetch("add", params);
    }

    async change(data, where) {
        const params = {};
        params.method = 'POST';

        const body = {
            token: JsonPHP.token
        };
        body.data = JSON.stringify(data);
        if (typeof where === 'object' && where.constructor.name === 'Array') {
            body.where = JSON.stringify(this.arrayToWhere(where));
        }
        params.body = JsonPHP.make_fd(body);

        return this.fetch("change", params);
    }

    async delete(where) {
        const params = {};
        params.method = 'POST';

        const body = {
            token: JsonPHP.token
        };
        if (typeof where === 'object' && where.constructor.name === 'Array') {
            body.where = JSON.stringify(this.arrayToWhere(where));
        }
        params.body = JsonPHP.make_fd(body);

        return this.fetch("delete", params);
    }
}

export { JsonPHP };
