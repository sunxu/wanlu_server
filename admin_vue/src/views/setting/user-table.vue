<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.user_name" placeholder="用户名称" style="width: 200px;" class="filter-item" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
      <el-button class="filter-item" style="margin-left: 10px;" type="primary" icon="el-icon-edit" @click="handleCreate">
        新增用户
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
      <el-table-column label="用户名称"  align="center">
        <template slot-scope="scope">
          {{ scope.row.user_name }}
        </template>
      </el-table-column>
      <el-table-column label="角色"  align="center">
        <template slot-scope="scope">
          <el-tag v-for="role in scope.row.roles">
            {{ role.role_name }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="账号状态"  align="center">
        <template slot-scope="scope">
          <el-tag :type="scope.row.status === 1?'success':'danger'">
            {{ scope.row.status? '正常' : '冻结' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="上次登陆时间" align="center">
        <template slot-scope="scope">
          {{ parseTime(scope.row.login_time) }}
        </template>
      </el-table-column>
      <el-table-column label="上次修改时间" align="center">
        <template slot-scope="scope">
          {{ parseTime(scope.row.updated_time) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="230" class-name="small-padding fixed-width">
        <template slot-scope="{row}">
          <el-dropdown @command="handleCommand">
            <el-button type="primary">
              操作<i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item :command="{ row:row, type: 'update' }">编辑</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'password' }">修改密码</el-dropdown-item>
              <el-dropdown-item :command="{ row:row, type: 'status' }">{{ row.status === 1 ? '冻结' : '开启' }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />

    <el-dialog :visible.sync="dialogFormVisible" :title="dialogStatus==='create'?'新增用户':'编辑用户'">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-width="80px" label-position="left">
        <el-form-item label="用户名称" prop="user_name">
          <el-input v-model="temp.user_name" placeholder="用户名称" />
        </el-form-item>
        <el-form-item label="密码" prop="password" v-show="dialogStatus==='create'">
          <el-input v-model="temp.password" type="password" placeholder="密码" />
        </el-form-item>
        <el-form-item label="APP" prop="apps">
          <el-select v-model="temp.app_ids" multiple placeholder="请选择 APP">
            <el-option
              v-for="app in apps"
              :key="app.app_id"
              :label="app.app_name"
              :value="app.app_id">
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="角色" prop="roles">
          <el-select v-model="temp.role_ids" multiple placeholder="请选择角色">
            <el-option
              v-for="role in rolesData"
              :key="role.role_id"
              :label="role.role_name"
              :value="role.role_id">
            </el-option>
          </el-select>
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button type="danger" @click="dialogFormVisible=false">取消</el-button>
        <el-button type="primary" @click="dialogStatus==='create'?createData():updateData()">
          保存
        </el-button>
      </div>
    </el-dialog>
    <el-dialog :visible.sync="passwordFormVisible" title="修改密码">
      <el-form ref="passwordForm" :rules="editPasswordFormRules" :model="editPasswordForm" label-width="80px" label-position="left">
        <el-form-item label="密码" prop="password">
          <el-input v-model="editPasswordForm.password" type="password" placeholder="密码" />
        </el-form-item>
        <el-form-item label="确认密码" prop="retry_password">
          <el-input v-model="editPasswordForm.retry_password" type="password" placeholder="确认密码" />
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button type="danger" @click="passwordFormVisible=false">取消</el-button>
        <el-button type="primary" @click="editPassword">
          保存
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { getList, storeUser, editUser, editPassword, editStatus } from '@/api/setting/user'
import { getList as roleList } from '@/api/setting/role'
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'

const defaultTemp = {
  user_id: undefined,
  user_name: undefined,
  password: undefined,
  role_ids: [],
  app_ids: []
}
export default {
  components: { Pagination },
  data() {
    return {
      rules: {
        user_name: [{ required: true, message: '请输入用户名称', trigger: 'blur' }],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ]
      },
      list: null,
      total: 0,
      listLoading: true,
      listQuery: {
        page: 1,
        per_page: 10,
        user_name: undefined
      },
      dialogStatus: '',
      dialogFormVisible: false,
      temp: Object.assign({}, defaultTemp),
      roles: [],
      apps: [],
      defaultProps: {
        children: 'children',
        label: 'title'
      },
      passwordFormVisible: false,
      editPasswordForm: {
        user_id: undefined,
        password: undefined,
        retry_password: undefined
      },
      editPasswordFormRules: {
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ],
        retry_password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ]
      }
    }
  },
  computed: {
    rolesData() {
      return this.roles
    }
  },
  created() {
    this.fetchData()
    this.getRoles()
    this.getApps()
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
    getRoles() {
      roleList({ per_page: 1000 }).then(response => {
        this.roles = response.data.records
      })
    },
    getApps() {
      this.apps = this.$store.getters.userInfo.apps
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    parseTime(time) {
      return parseTime(time)
    },
    resetTemp() {
      this.temp = {
        user_id: undefined,
        user_name: undefined,
        password: undefined,
        role_ids: [],
        app_ids: []
      }
    },
    handleCreate() {
      this.resetTemp()
      this.dialogStatus = 'create'
      this.dialogFormVisible = true
    },
    createData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          storeUser(this.temp).then(response => {
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
    handleUpdate(row) {
      const roleIds = []
      row.roles.forEach(role => {
        roleIds.push(role.role_id)
      })
      row.role_ids = roleIds
      row.password = '123456'
      const appIds = []
      row.apps.forEach(app => {
        appIds.push(app.app_id)
      })
      row.app_ids = appIds
      this.temp = Object.assign(defaultTemp, row)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
    },
    updateData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          const userId = this.temp.user_id
          this.temp.user_id = undefined
          this.temp.password = undefined
          editUser(userId, this.temp).then(response => {
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
    handleCommand(item) {
      switch (item.type) {
        case 'update':
          this.handleUpdate(item.row)
          break
        case 'password':
          this.handleEditPassword(item.row.user_id)
          break
        case 'status':
          this.editStatus(item.row.user_id)
          break
      }
    },
    editStatus(userId) {
      editStatus(userId).then(response => {
        const index = this.list.findIndex(v => v.user_id === userId)
        this.list[index].status ^= 1 ^ 0
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    handleEditPassword(userId) {
      this.passwordFormVisible = true
      this.editPasswordForm.user_id = userId
      this.editPasswordForm.password = undefined
      this.editPasswordForm.retry_password = undefined
    },
    editPassword() {
      this.$refs.passwordForm.validate((valid) => {
        if (valid) {
          const userId = this.editPasswordForm.user_id
          this.editPasswordForm.user_id = undefined
          editPassword(userId, this.editPasswordForm).then(response => {
            this.handleFilter()
            this.passwordFormVisible = false
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
