<template>
    <div class="file-table">
    <div v-if="isShowSpinner" class="spinner-container">
        <div class="sk-spinner sk-spinner-double-bounce">
            <div class="sk-double-bounce1"></div>
            <div class="sk-double-bounce2"></div>
        </div>
    </div>

    <div class="row files-tools">
        <div class="col-md-6 col-xs-12">

            <div class="input-group m-b inner-addon right-addon">
                <div class="input-group-btn">
                    <button data-toggle="dropdown" class="btn btn-white dropdown-toggle" type="button">All folders <span class="caret"></span></button>
                    <ul class="dropdown-menu">
                        <li><a href="#">All folders</a></li>
                        <li><a href="#">Another action</a></li>
                    </ul>
                </div>
                <input placeholder="search" type="text" class="form-control" @keyup.enter="doSearch()" v-model="search">
                <i class="glyphicon glyphicon-search"></i>
                 <!--<span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>-->
            </div>
        </div>
        <div class="col-md-6 col-xs-12">
            <div class="pull-right">
                <span class="create-folder-btn" @click="createFolder()"><i class="fa fa-folder-o"></i> Create folder</span>
                <form method="post" action="/files/upload-file" enctype="multipart/form-data" class="inline-block" id="file-upload-form">
                    <div class="fileinput fileinput-new" data-provides="fileinput">
                        <span class="btn btn-primary btn-file"><span class="fileinput-new">Upload file</span>
                        <input @change="fileChange($event.target.name, $event.target.files);" type="file" name="fileUpload"/></span>
                        <span class="fileinput-filename"></span>
                        <a href="#" class="close fileinput-exists" data-dismiss="fileinput" style="float: none">×</a>
                    </div>
                </form>
            </div>
        </div>
        <!--<div class="col-md-2">-->
            <!--<button class="btn btn-primary" @click="doSearch()">Search</button>-->
        <!--</div>-->
    </div>
    <!--<div class="row">-->
        <!--<div class="col-md-8">-->
            <!--<label><input type="radio" name="orderBy" value="Name"/>Name</label>-->
            <!--<label><input type="radio" name="orderBy" value="Size"/>Size</label>-->
            <!--<label><input type="radio" name="orderBy" value=""/>Created At</label>-->
            <!--<label><input type="radio" name="orderBy" />Owner</label>-->
        <!--</div>-->
    <!--</div>-->
    <table class="table files-table">
        <thead>
        <tr>
            <th class="space-td"></th>
            <th class="files-td-icon"></th>
            <th><span class="sortable-header" @click="sort('name')">Name <i class="fa" :class="getOrderClass('name')"></i></span></th>
            <!--<th><span class="sortable-header" @click="sort('length')">Size <i class="fa" :class="getOrderClass('length')"></i></span></th>-->
            <th><span class="sortable-header" @click="sort('createdAt.date')">Created At <i class="fa" :class="getOrderClass('createdAt.date')"></i></span></th>
            <th><span class="sortable-header" @click="sort('ownerName')">Owner <i class="fa" :class="getOrderClass('ownerName')"></i></span></th>
            <!--<th>Permissions</th>-->
            <!--<th class="text-center">Shared</th>-->
            <th>Actions</th>
            <th class="space-td"></th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="treeItem in filteredFiles" v-bind:class="{ disabled: treeItem.isDeleted }">
            <td class="space-td"></td>
            <td class="files-td-icon">
                <!--<a :href="treeItem.link">{{ treeItem.id }}</a>-->
                <i v-if="treeItem.fileType == 'directory'" class="fa fa-folder-o" title="directory" aria-hidden="true"></i>
                <i v-else class="fa fa-file-o" title="file" aria-hidden="true"></i>
            </td>
            <!--<td class="text-center">-->
                <!--<i v-if="treeItem.fileType == 'directory'" class="fa fa-folder-o" title="directory" aria-hidden="true"></i>-->
                <!--<i v-else class="fa fa-file-o" title="file" aria-hidden="true"></i>-->
            <!--</td>-->
            <td>
                <div v-if="!treeItem.isDeleted">
                    <input v-if="treeItem.isNew" v-focus autocomplete="off" @blur="folderBlur(treeItem)" class="form-control" placeholder="input folder name" type="text" v-model="treeItem.name" @keyup="folderKeyUp($event, treeItem)"/>
                    <span v-else><a :href="treeItem.link">{{ treeItem.name }}</a></span>
                </div>
                <div v-else>
                    <span>{{ treeItem.name }}</span>
                </div>
            </td>
            <!--<td>{{ getSize(treeItem) }}</td>-->
            <td>{{ treeItem.createdAt ? formatDate(treeItem.createdAt.date) : '' }}</td>
            <td>{{ treeItem.ownerName }}</td>
            <!--<td class="text-center">{{ getRights(treeItem) }}</td>-->
            <!--<td class="text-center">-->
                <!--<i v-if="treeItem.shareStatus == 'shared'" class="fa fa-check" title="shared" aria-hidden="true"></i>-->
                <!--<i v-else class="fa fa-minus" title="not shared" aria-hidden="true"></i>-->
            <!--</td>-->
            <td class="files-action-td">
                <div class="input-group" v-if="!treeItem.isDeleted">
                    <!--<button v-if="treeItem.fileType == 'directory'" class="btn btn-default form-control">List elements</button>-->
                    <a v-if="treeItem.fileType == 'directory'" :href="'/files/edit/' + treeItem.id" class="btn btn-default form-control">List elements</a>
                    <!--<button v-else class="btn btn-default form-control">Edit</button>-->
                    <a v-else class="btn btn-default form-control" :href="'/files/edit/' + treeItem.id">Edit</a>
                    <div class="input-group-btn">
                        <button data-toggle="dropdown" class="btn btn-default dropdown-toggle file-more-actions" type="button"><i class="fa fa-ellipsis-h"></i></button>
                        <!--<span class="input-group-addon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-ellipsis-h"></i></span>-->
                        <ul class="dropdown-menu">
                            <li><a @click="deleteItem(treeItem)">Delete</a></li>
                            <li><a >Another action</a></li>
                        </ul>
                    </div>
                </div>
                <button v-else class="form-control btn btn-default" @click="restoreItem(treeItem)">Restore</button>
            </td>
            <td class="space-td"></td>
        </tr>
        </tbody>
    </table>
    </div>
</template>

<script>

    var moment = require('moment');
    var axios = require('axios');
    var toastr = require('toastr');

    Vue.directive('focus', {
        // Когда привязанный элемент вставлен в DOM...
        inserted: function (el) {
            // Переключаем фокус на элемент
            el.focus()
        }
    })

    export default {
        name: 'file-table',
        props: ['fileList', 'initSortField', 'initSortOrder', 'parentId', 'baseLink'],
        data() {
            return {
                search: '',
                files: [],
                sortFieldName: '',
                sortFieldOrder: '',
                isShowSpinner: false,
                crsf: ''
            }
        },
        methods: {
            getSize(item) {
                return item.length;
            },
            getRights(item) {
                var result = '';
                var tmp = [];
                if (item.rights['total']) {
                    Object.keys(item.rights['total']).forEach(function (item, index, arr) {
                        tmp.push(item.split('.')[0]);
                    });
                }
                result = tmp.join('|');
                return result;
            },
            formatDate(value) {
                if (value) {
                    return moment(String(value)).format('DD.MM.YYYY');
                }
                else{
                    return '';
                }
            },
            sort(fieldName) {
                if (fieldName == this.sortFieldName) {
                    this.sortFieldOrder = (this.sortFieldOrder == 'asc' ? 'desc' : 'asc');
                }
                else {
                    this.sortFieldName = fieldName;
                    this.sortFieldOrder = 'asc';
                }

                axios.get('/files/set-order', {
                    params: {
                        field: this.sortFieldName,
                        order: this.sortFieldOrder
                    }
                })
                .then(function (response) {
                    console.log(response);
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            getOrderClass(fieldName){
                var result = 'fa-sort-desc';
                if (this.sortFieldName == fieldName){
                    result = 'active fa-sort-' + this.sortFieldOrder;
                }
                return result;
            },
            doSearch() {
                if (this.search) {
                    var link = '/files/search?query=' + this.search;
                    window.location.href = link;
                }
            },
            createFolder(){
                this.files.push({
                    id: Date.now(),
                    name: '',
                    fileType: 'directory',
                    date: '',
                    isNew: true
                })
            },
            removeItem(item){
                if (this.files.indexOf(item) >= 0) {
                    this.files.splice(this.files.indexOf(item), 1)
                }
            },
            addFolder(folder) {
                //con
                if (folder.name) {
                    var vueInstance = this;
                    var parentId = this.parentId;
                    var baseLink = this.baseLink;
                    axios.post('/files/add-folder', {
                        name: folder.name,
                        parentId: parentId,
                        path: baseLink
                    })
                    .then(function (response) {
                        if (response.data.type == 'success'){
                            vueInstance.removeItem(folder);
                            vueInstance.files.push(response.data.data);
                            toastr.success('Folder has been added');
                        }
                        else{
                            toastr.error(response.data.msg);
                        }
                        console.log(response);
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                }
            },
            folderKeyUp(event, element) {
                if (event.keyCode == 13){
                    event.target.blur();
                    //console.log(key);
                    //this.addFolder(element);
                    //this.showSpinner();
                }
                else if (event.keyCode == 27){
                    this.removeItem(element)
                }
            },
            folderBlur(item){
                if (item.name){
                    this.addFolder(item);
                }
                else{
                    this.removeItem(item);
                }
            },
            showSpinner(){
                this.isShowSpinner = true;
            },
            hideSpinner(){
                this.isShowSpinner = false;
            },
            uploadFile(formData){
                var vueInstance = this;
                axios.post('/files/upload-file/', formData)
                .then(function (response) {
                    console.log(response);
                    if (response) {
                        if (response.data.type == 'success') {
//                            vueInstance.removeItem(folder);
                            vueInstance.files.push(response.data.data);
                            toastr.success('File has been added');
                        }
                        else {
                            toastr.error(response.data.msg);
                        }
                    }
                    else {
                        toastr.error('System Error');
                    }
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            fileChange(name, files){
                var maxFileSize = '10485760'; //10MB
                var form = document.getElementById('file-upload-form');
                var formData = new FormData(form);
                var file = files[0];
                if (file.size > maxFileSize){
                    toastr.error('File size should be less than or equal ' + (maxFileSize / (1024*1024)) + 'MB');
                }
                else{
                    formData.append('parentId', this.parentId);
                    this.uploadFile(formData);
                }
            },
            deleteItem(item) {
                if (confirm('The file would be unavailable for users and mobile apps. Are you sure?')) {
                    var vueInstance = this;
                    axios.post('/files/delete', {
                        fileId: item.id
                    })
                    .then(function (response) {
                        if (response.data.type == 'success'){

                            vueInstance.removeItem(item);
                            item.isDeleted = true;
                            vueInstance.files.push(item);
                            toastr.success('Item has been deleted');
                        }
                        else{
                            toastr.error(response.data.msg);
                        }
                        console.log(response);
                    })
                    .catch(function (error) {
                        toastr.error('System error');
                        console.log(error);
                    });
                }
            },
            restoreItem(item) {
                if (confirm('Are you sure you want to restore it?')) {
                    var vueInstance = this;
                    axios.post('/files/restore', {
                        fileId: item.id
                    })
                        .then(function (response) {
                            if (response.data.type == 'success'){

                                vueInstance.removeItem(item);
                                item.isDeleted = false;
                                vueInstance.files.push(item);
                                toastr.success('Item has been restored');
                            }
                            else{
                                toastr.error(response.data.msg);
                            }
                            console.log(response);
                        })
                        .catch(function (error) {
                            toastr.error('System error');
                            console.log(error);
                        });
                }
            }
        },
        computed:{
            filteredFiles: function() {
                var searchText = this.search;
                var result = [];
                if (searchText) {
                    result = _.pickBy(this.files, function (value, key) {
                        return _.includes(value.name, searchText) ||
                               _.includes(value.id, searchText) ||
                               _.includes(value.ownerName, searchText);
                    });
                }
                else{
                    result = this.files;
                }
                var orderNames = ['fileType', 'isNew'];
                var orderOrders = ['asc', 'desc'];
                if (this.sortFieldName){
                    orderNames.push(this.sortFieldName);
                    orderOrders.push(this.sortFieldOrder);
                }
                result = _.orderBy(result, orderNames, orderOrders);
                return result;
            }
        },
        mounted() {
            this.sortFieldName = this.initSortField;
            this.sortFieldOrder = this.initSortOrder;
            this.files = _.values(this.fileList);

            var vueIntance = this;

            axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            this.crsf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            axios.interceptors.request.use(request => {
                //console.log('Starting Request', request)
                vueIntance.showSpinner();
                return request;
            })

            axios.interceptors.response.use(response => {
                //console.log('Response:', response)
                vueIntance.hideSpinner();
                return response;
            },
            error =>{
                vueIntance.hideSpinner();
            })
            //this.files = _.orderBy(this.fileList, ['fileType'], ['asc']);
        }
    }
</script>

<style>
    .sortable-header{
        cursor: pointer;
    }

    .sortable-header:hover{
        color: #2f4050;
    }
</style>