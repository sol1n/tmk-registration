<template>
    <div class="permissions">
        <table class="table">
            <thead>
                <tr>
                    <th>User/Role</th>
                    <th>Permissions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(permission, id) in permissions">
                    <td>
                        <select class="form-control" :name="'permissions[' + id + '][id]'" required v-model="permission['id']">
                            <optgroup label="users">
                                <option v-for="(userName, userId) in users" :value="'user.' + userId" :selected="userId == permission.id">{{ userName }}</option>
                            </optgroup>
                            <optgroup label="roles">
                                <option v-for="role in roles" :value="role" :selected="role == permission.id">{{ role }}</option>
                            </optgroup>
                        </select>
                    </td>
                    <td>
                        <div class="checkbox-inline">
                            <label><input  :name="'permissions[' + id + '][rights][]'" type="checkbox" v-model="permission.rights.READ" value="READ">READ</label>
                        </div>
                        <div class="checkbox-inline">
                            <label><input :name="'permissions[' + id + '][rights][]'" type="checkbox" v-model="permission.rights.WRITE" value="WRITE">WRITE</label>
                        </div>
                        <div class="checkbox-inline">
                            <label><input :name="'permissions[' + id + '][rights][]'" type="checkbox" v-model="permission.rights.DELETE" value="DELETE">DELETE</label>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-default" type="button" @click="removePermission(permission)">delete</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div>
            <button class="btn btn-primary" type="button" @click="addRight()">Add right</button>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'permissions',
        props: ['initPermissions', 'users', 'roles'],
        data() {
            return {
                permissions: [],
            }
        },
        methods: {
            isChecked(rights, right) {
                return _.includes(rights, right);
            },
            addRight() {
                var id = 'user.' + Object.keys(this.users)[0];
                var newObject = {
                    'id' : id,
                    'rights' : {
                        'READ' : false,
                        'WRITE' : false,
                        'DELETE' : false
                    }
                };
                this.permissions.push(newObject);
            },
            removePermission(item) {
                this.permissions.splice(this.permissions.indexOf(item), 1);
            }
        },
        mounted() {
//            this.permissions = _.values(this.initPermissions);

            var permissions = [];
            _.forEach(this.initPermissions, function(element, index,){
                var id = element.id;
                var rights = {};
                _.forEach(element.rights, function(right, rightIndex){
                    rights[right] = true;
                });
                permissions.push({
                    'id': id ,
                    'rights': rights
                });
            });
            this.permissions = permissions;
        }
    }
</script>
