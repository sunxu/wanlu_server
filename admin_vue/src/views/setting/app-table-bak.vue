<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="6">
          <!-- <span class="el-col-span">应用信息：</span> -->
          <el-input v-model="listQuery.app_name" placeholder="APP名称/标识/appid" clearable size="small" style="width: 240px;" class="filter-item" />
        </el-col>
        <el-col :span="6">
          <!-- <span class="el-col-span">应用状态：</span> -->
          <el-select v-model="listQuery.status" placeholder="应用状态" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in statusMap" :key="key" :label="value" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="6">
          <!-- <span class="el-col-span">投放状态：</span> -->
          <el-select v-model="listQuery.ad_status" placeholder="投放状态" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in adStatusMap" :key="key" :label="value" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="updateShow(detailDefault)">
            新增
          </el-button>
        </el-col>
      </el-row>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column label="ID"  align="center" width="60">
        <template slot-scope="scope">
          {{ scope.row.app_id }}
        </template>
      </el-table-column>
      <el-table-column label="应用信息"  align="left" width="300">
        <template slot-scope="scope">
          <div><b>标识：</b>{{ scope.row.app_code }}</div>
          <div><b>名称：</b>{{ scope.row.app_name }}</div>
          <div><b>包名：</b>{{ scope.row.package_name }}</div>
        </template>
      </el-table-column>
      <el-table-column label="接入信息"  align="left" width="300">
        <template slot-scope="scope">
          <div><b>appid：</b>{{ scope.row.sign_secret_key }}</div>
          <div><b>secret：</b>{{ scope.row.params_secret_key }}</div>
        </template>
      </el-table-column>
      <el-table-column label="应用状态"  align="center">
        <template slot-scope="scope" style="">
            <el-tag v-if="scope.row.status === 0" type="danger">停用</el-tag>
            <el-tag v-if="scope.row.status === 1" type="success">启用</el-tag>

            <el-tag v-if="scope.row.ad_status === 0" type="danger">停投</el-tag>
            <el-tag v-if="scope.row.ad_status === 1" type="success">自投</el-tag>
            <el-tag v-if="scope.row.ad_status === 2">代投</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="最后修改时间" align="center">
        <template slot-scope="scope">
          {{ parseTime(scope.row.updated_time) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="230" class-name="small-padding fixed-width">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdateAuth(row)">
            编辑权限
          </el-button>
        </template>
        <template slot-scope="{row}">
          <el-dropdown @command="handleCommand">
            <el-button type="primary">
              操作<i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item :command="{ row:row, type: 'detail' }">编辑</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'update' }">权限</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'adstatus' }">{{ row.ad_status === 1 ? '停投' : '自投' }}</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'adstatus2' }">{{ row.ad_status === 2 ? '停投' : '代投' }}</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'status' }">{{ row.status === 1 ? '停用' : '启用' }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />

    <el-dialog :visible.sync="dialogFormVisible" :title="'编辑权限:【' + auth.app_name+ '】'">
      <el-form ref="dataForm" :model="auth" label-width="80px" label-position="left">
        <el-form-item label="权限">
          <el-tree
            ref="tree"
            :check-strictly="false"
            :data="routesData"
            :props="defaultProps"
            show-checkbox
            node-key="authName"
            class="permission-tree"
          />
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button type="danger" @click="dialogFormVisible=false">取消</el-button>
        <el-button type="primary" @click="updateAuth()">保存</el-button>
      </div>
    </el-dialog>

    <el-dialog :visible.sync="dialogDetailVisible" title="应用信息" width="600px">
      <el-form label-position="left" label-width="150px" :model="detailApp">
        <el-form-item label="应用标识">
          <el-input v-model="detailApp.app_code" :disabled="detailApp.app_id > 0" style="width: 90%" />
          <el-input v-model="detailApp.app_id" v-show="false" />
        </el-form-item>

        <el-form-item label="appid" v-if="detailApp.app_id > 0" >
          <el-input v-model="detailApp.sign_secret_key" disabled style="width: 90%" />
        </el-form-item>
        <el-form-item label="secret" v-if="detailApp.app_id > 0" >
          <el-input v-model="detailApp.params_secret_key" disabled style="width: 90%" />
        </el-form-item>
        <el-form-item label="应用名称">
          <el-input v-model="detailApp.app_name" style="width: 90%" />
        </el-form-item>
        <el-form-item label="应用包名">
          <el-input v-model="detailApp.package_name" style="width: 90%" />
        </el-form-item>
        <el-form-item label="应用状态">
          <el-radio-group v-model="detailApp.status">
            <el-radio-button
              v-for="(value,key) in statusMap"
              :label="key"
              border
            >{{ value }}
            </el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="投放状态">
          <el-radio-group v-model="detailApp.ad_status">
            <el-radio-button
              v-for="(value,key) in adStatusMap"
              :label="key"
              border
            >{{ value }}
            </el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button @click="dialogDetailVisible = false">取 消</el-button>
        <el-button type="primary" @click="updateApp">确 定</el-button>
      </div>
    </el-dialog>

  </div>
</template>

<script>
import { getList, editAuth, editStatus, editAdstatus, editAdstatus2, saveApp } from '@/api/setting/app'
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { asyncRoutes } from '@/router'

export default {
  components: { Pagination },
  data() {
    return {
      statusMap: {
        '0': '停用',
        '1': '启用'
      },
      adStatusMap: {
        '0': '停投',
        '1': '自投',
        '2': '代投'
      },
      list: null,
      total: 0,
      listLoading: true,
      listQuery: {
        page: 1,
        per_page: 10,
        app_name: undefined,
        ad_status: undefined
      },
      dialogFormVisible: false,
      auth: {
        app_id: undefined,
        app_name: undefined,
        pages: [],
        apis: []
      },
      routes: [],
      serviceRoutes: [],
      defaultProps: {
        children: 'children',
        label: 'title'
      },
      dialogDetailVisible: false,
      detailDefault: {
        app_id: undefined,
        app_code: undefined,
        app_name: undefined,
        sign_secret_key: undefined,
        params_secret_key: undefined,
        status: 1,
        ad_status: 0,
        package_name: undefined
      },
      detailApp: {}
    }
  },
  computed: {
    routesData() {
      return this.routes
    }
  },
  created() {
    this.fetchData()
    this.getRoutes()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      getList(this.listQuery).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    getRoutes() {
      let routes = []
      if (this.$store.getters.userInfo.is_super === 1) {
        routes = asyncRoutes
      } else {
        routes = this.$router.options.routes
      }
      this.serviceRoutes = this.filterAsyncRoutes(routes)
      this.routes = this.generateRoutes(this.serviceRoutes)
    },
    filterAsyncRoutes(routes) {
      const res = []

      routes.forEach(route => {
        const tmp = { ...route }
        if (tmp.meta && tmp.meta.authName && tmp.meta.authType !== 'common') {
          if (tmp.children) {
            tmp.children = this.filterAsyncRoutes(tmp.children)
          }
          res.push(tmp)
        }
      })
      return res
    },
    generateRoutes(routes) {
      const res = []

      for (let route of routes) {
        const data = {
          authName: route.meta.authName,
          title: route.meta && route.meta.title

        }

        // recursive child routes
        if (route.children) {
          data.children = this.generateRoutes(route.children, data.path)
        }
        res.push(data)
      }
      return res
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    parseTime(time) {
      return parseTime(time)
    },
    generateTree(checkedKeys, routers) {
      const res = []
      routers.forEach(route => {
        const tmp = { ...route }
        for (const key of checkedKeys) {
          if (key === tmp.meta.authName && tmp.meta.apis) {
            res.push(...tmp.meta.apis)
          }
        }
        if (tmp.children !== undefined && tmp.children.length > 0) {
          res.push(...this.generateTree(checkedKeys, tmp.children))
        }
      })
      return res
    },
    handleCommand(item) {
      switch (item.type) {
        case 'detail':
          this.updateShow(item.row)
          break
        case 'update':
          this.handleUpdateAuth(item.row)
          break
        case 'adstatus':
          this.editAdstatus(item.row.app_id)
          break
        case 'adstatus2':
          this.editAdstatus2(item.row.app_id)
          break
        case 'status':
          this.editStatus(item.row.app_id)
          break
      }
    },
    editStatus(appId) {
      editStatus(appId).then(response => {
        const index = this.list.findIndex(v => v.app_id === appId)
        this.list[index].status ^= 1 ^ 0
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    editAdstatus(appId) {
      editAdstatus(appId).then(response => {
        const index = this.list.findIndex(v => v.app_id === appId)
        this.list[index].ad_status  = response.data.ad_status
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    editAdstatus2(appId) {
      editAdstatus2(appId).then(response => {
        const index = this.list.findIndex(v => v.app_id === appId)
        this.list[index].ad_status = response.data.ad_status
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    handleUpdateAuth(row) {
      this.auth.pages = row.auth && row.auth.pages ? row.auth.pages : []
      this.auth.apis = row.auth && row.auth.apis ? row.auth.apis : []
      this.auth.app_id = row.app_id
      this.auth.app_name = row.app_name
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.$refs.tree.setCheckedKeys(this.auth.pages)
      })
    },
    updateAuth() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          const authApis = this.generateTree(this.$refs.tree.getCheckedKeys(), this.serviceRoutes)
          this.auth.pages = this.$refs.tree.getCheckedKeys()
          this.auth.apis = authApis
          const appId = this.auth.app_id
          this.auth.app_id = undefined
          this.auth.role_name = undefined
          editAuth(appId, this.auth).then(response => {
            this.fetchData()
            this.dialogFormVisible = false
            Message({
              showClose: true,
              message: 'success',
              type: 'success',
              duration: 5 * 1000
            })
          })
        }
      })
    },
    updateShow(item = {}) {
      this.detailApp = _.clone(item)
      this.dialogDetailVisible = true
    },
    updateApp() {
      console.log(this.detailApp)
      saveApp(this.detailApp).then(response => {
        Message({
          message: '保存成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.dialogDetailVisible = false
        this.handleFilter()
      }).catch(() => {
      })
    }
  }
}
</script>

<style scoped>
.el-row {
  margin-bottom: 10px;
}

.el-radio {
  margin: 0
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
