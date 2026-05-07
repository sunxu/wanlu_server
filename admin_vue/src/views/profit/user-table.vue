<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">用户 ID：</span>
          <el-input v-model="listQuery.user_id" placeholder="用户 ID" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">注册渠道：</span>
          <el-input v-model="listQuery.channel_id" placeholder="注册渠道" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放渠道：</span>
          <el-select v-model="listQuery.source" placeholder="投放渠道" clearable style="width: 130px" class="filter-item">
            <el-option v-for="item in sources" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放方式：</span>
          <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable class="filter-item" style="width: 200px">
            <el-option v-for="item in launch_types" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放计划：</span>
          <el-input v-model="listQuery.plan_id" placeholder="投放计划" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">手机厂商：</span>
          <el-select v-model="listQuery.vendor_type" placeholder="手机厂商" clearable class="filter-item" style="width: 200px">
            <el-option v-for="item in vendor_types" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">注册版本：</span>
          <el-input v-model="listQuery.app_version" placeholder="注册版本" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">注册日期：</span>
          <el-date-picker v-model="created_time" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">最近启动：</span>
          <el-date-picker v-model="last_login" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">累计登录（天）：</span>
          <el-input v-model="listQuery.login_count_begin" placeholder="开始" style="width: 92px;" class="filter-item" />
          <label>～</label>
          <el-input v-model="listQuery.login_count_end" placeholder="结束" style="width: 92px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">账号等级：</span>
          <el-input v-model="listQuery.grade" placeholder="账号等级" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">账户余额（金币）：</span>
          <el-input v-model="listQuery.gold_coin_begin" placeholder="开始" style="width: 92px;" class="filter-item" />
          <label>～</label>
          <el-input v-model="listQuery.gold_coin_end" placeholder="结束" style="width: 92px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">账户余额（元宝）：</span>
          <el-input v-model="listQuery.gold_ingot_begin" placeholder="开始" style="width: 92px;" class="filter-item" />
          <label>～</label>
          <el-input v-model="listQuery.gold_ingot_end" placeholder="结束" style="width: 92px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">累计提现次数(到账)：</span>
          <el-input v-model="listQuery.withdrawal_count_success" placeholder="累计提现次数(到账)" style="width: 200px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">累计提现（元）：</span>
          <el-input v-model="listQuery.withdrawal_amount_success_begin" placeholder="开始" style="width: 92px;" class="filter-item" />
          <label>～</label>
          <el-input v-model="listQuery.withdrawal_amount_success_end" placeholder="结束" style="width: 92px;" class="filter-item" />
        </el-col>
      </el-row>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
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
      <el-table-column label="用户 ID"  align="center">
        <template slot-scope="scope">
          {{ scope.row.user_id }}
        </template>
      </el-table-column>
      <el-table-column label="注册渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.channel_id }}
        </template>
      </el-table-column>
      <el-table-column label="投放渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.source }}
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center">
        <template slot-scope="scope">
          {{ scope.row.launch_type }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="投放计划">
        <template slot-scope="scope">
          {{ scope.row.plan_id }}
        </template>
      </el-table-column>
      <el-table-column label="手机厂商"  align="center">
        <template slot-scope="scope">
          {{ scope.row.vendor_type }}
        </template>
      </el-table-column>
      <el-table-column label="注册版本"  align="center">
        <template slot-scope="scope">
          {{ scope.row.app_version }}
        </template>
      </el-table-column>
      <el-table-column label="注册时间"  align="center">
        <template slot-scope="scope">
          {{ scope.row.created_time }}
        </template>
      </el-table-column>
      <el-table-column label="最近一次启动时间"  align="center">
        <template slot-scope="scope">
          {{ scope.row.last_login }}
        </template>
      </el-table-column>
      <el-table-column label="累计登录时长（天）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.login_count }}
        </template>
      </el-table-column>
      <el-table-column label="账号等级"  align="center">
        <template slot-scope="scope">
          {{ scope.row.grade }}
        </template>
      </el-table-column>
      <el-table-column label="账户余额（金币）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.gold_coin }}
        </template>
      </el-table-column>
      <el-table-column label="账户余额（元宝）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.gold_ingot }}
        </template>
      </el-table-column>
      <el-table-column label="累计提现次数"  align="center">
        <template slot-scope="scope">
          {{ scope.row.withdrawal_count_success }}
        </template>
      </el-table-column>
      <el-table-column label="累计提现（元）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.withdrawal_amount_success }}
        </template>
      </el-table-column>
<!--      <el-table-column label="操作"  align="center">-->
<!--        <template slot-scope="scope">-->
<!--          {{ scope.row.withdrawal__success }}-->
<!--        </template>-->
<!--      </el-table-column>-->
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>
<style>
.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
<script>
import { getList } from '@/api/profit/user'
import Pagination from '@/components/Pagination'
import { getCurrentApp } from '@/utils/auth'

export default {
  components: { Pagination },
  data() {
    return {
      list: null,
      total: 0,
      listLoading: true,
      created_time: undefined,
      last_login: undefined,
      listQuery: {
        page: 1,
        per_page: 10,
        appcode: undefined,
        user_id: undefined,
        channel_id: undefined,
        source: undefined,
        launch_type: undefined,
        plan_id: undefined,
        vendor_type: undefined,
        app_version: undefined,
        created_time_begin: undefined,
        created_time_end: undefined,
        last_login_begin: undefined,
        last_login_end: undefined,
        login_count_begin: undefined,
        login_count_end: undefined,
        grade: undefined,
        gold_coin_begin: undefined,
        gold_coin_end: undefined,
        gold_ingot_begin: undefined,
        gold_ingot_end: undefined,
        withdrawal_count_success: undefined,
        withdrawal_amount_success_begin: undefined,
        withdrawal_amount_success_end: undefined
      },
      sources: [
        { code: 'ks', name: '快手' },
        { code: 'jl', name: '巨量' }
      ],
      launch_types: [
        { code: '0', name: '双出' },
        { code: '1', name: '关键行为' }
      ],
      vendor_types: [
        { code: 'xiaomi', name: '小米' },
        { code: 'huawei', name: '华为' },
        { code: 'oppo', name: 'oppo' },
        { code: 'vivo', name: 'vivo' },
        { code: 'other', name: '其他' }
      ]
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      getList(this.getListQuery()).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    getListQuery() {
      this.listQuery.appcode = getCurrentApp().app_code
      if (this.created_time) {
        this.listQuery.created_time_begin = this.created_time[0].valueOf() / 1000
        this.listQuery.created_time_end = this.created_time[1].valueOf() / 1000 + 60 * 60 * 24 - 1
      }
      if (this.last_login) {
        this.listQuery.last_login_begin = this.last_login[0].valueOf() / 1000
        this.listQuery.last_login_end = this.last_login[1].valueOf() / 1000 + 60 * 60 * 24 - 1
      }
      return this.listQuery
    }
  }
}
</script>
