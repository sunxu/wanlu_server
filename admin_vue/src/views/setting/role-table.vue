<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.role_name" placeholder="角色名称" style="width: 200px;" class="filter-item" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
      <el-button class="filter-item" style="margin-left: 10px;" type="primary" icon="el-icon-edit" @click="handleCreate">
        新增角色
      </el-button>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="ID" width="95">
        <template slot-scope="scope">
          {{ scope.$index + 1}}
        </template>
      </el-table-column>
      <el-table-column label="角色名称"  align="center">
        <template slot-scope="scope">
          {{ scope.row.role_name }}
        </template>
      </el-table-column>
      <el-table-column label="最后修改时间" align="center">
        <template slot-scope="scope">
          {{ parseTime(scope.row.updated_time) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="230" class-name="small-padding fixed-width">
        <template slot-scope="{row}">
          <el-button type="primary" size="mini" @click="handleUpdate(row)">
            编辑
          </el-button>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />

    <el-dialog :visible.sync="dialogFormVisible" :title="dialogStatus==='create'?'新增角色':'编辑角色'">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-width="80px" label-position="left">
        <el-form-item label="角色名称" prop="role_name">
          <el-input v-model="temp.role_name" placeholder="角色名称" />
        </el-form-item>
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
        <el-button type="primary" @click="dialogStatus==='create'?createData():updateData()">
          保存
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { getList, storeRole, editRole } from '@/api/setting/role'
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { asyncRoutes } from '@/router'

const defaultTemp = {
  role_id: undefined,
  role_name: undefined,
  pages: [],
  apis: []
}

export default {
  components: { Pagination },
  data() {
    return {
      rules: {
        role_name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }]
      },
      list: null,
      total: 0,
      listLoading: true,
      listQuery: {
        page: 1,
        per_page: 10,
        role_name: undefined
      },
      dialogStatus: '',
      dialogFormVisible: false,
      temp: Object.assign({}, defaultTemp),
      routes: [],
      serviceRoutes: [],
      defaultProps: {
        children: 'children',
        label: 'title'
      }
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
        if (tmp.meta && tmp.meta.authName) {
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
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
      if (this.$refs.tree) {
        this.$refs.tree.setCheckedNodes([])
      }
    },
    resetTemp() {
      this.temp = {
        role_id: undefined,
        role_name: undefined,
        pages: [],
        apis: []
      }
    },
    createData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          const authApis = this.generateTree(this.$refs.tree.getCheckedKeys(), this.serviceRoutes)
          this.temp.pages = this.$refs.tree.getCheckedKeys()
          this.temp.apis = authApis
          storeRole(this.temp).then(response => {
            this.handleFilter()
            this.dialogStatus = ''
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
    handleUpdate(row) {
      this.temp = Object.assign(defaultTemp, row)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.$refs.dataForm.clearValidate()
        this.$refs.tree.setCheckedKeys(this.temp.pages)
      })
    },
    updateData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          const authApis = this.generateTree(this.$refs.tree.getCheckedKeys(), this.serviceRoutes)
          this.temp.pages = this.$refs.tree.getCheckedKeys()
          this.temp.apis = authApis
          const roleId = this.temp.role_id
          this.temp.role_id = undefined
          editRole(roleId, this.temp).then(response => {
            this.handleFilter()
            this.dialogStatus = ''
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
    }
  }
}
</script>
