<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.goods_name" placeholder="套餐名称" style="width: 200px;" class="filter-item" />
      <el-input v-model="listQuery.goods_no" placeholder="套餐编号" style="width: 200px;" class="filter-item" />
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
      <el-button class="filter-item" style="margin-left: 10px;" type="primary" icon="el-icon-edit" @click="handleCreate">
        新增套餐
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
      <el-table-column align="center" label="套餐编号">
        <template slot-scope="scope">
          {{ scope.row.goods_no}}
        </template>
      </el-table-column>
      <el-table-column label="套餐名称"  align="center">
        <template slot-scope="scope">
          {{ scope.row.goods_name }}
        </template>
      </el-table-column>
      <el-table-column label="套餐价格"  align="center">
        <template slot-scope="scope">
          <span v-if="scope.row.is_discounts === 1">
            <s>¥{{ scope.row.goods_price }}</s>
            <b style="color: #ff4949"> ¥{{ scope.row.discounts_goods }}</b>
          </span>
          <span v-if="scope.row.is_discounts === 0">
            <b>¥{{ scope.row.goods_price }}</b>
          </span>
        </template>
      </el-table-column>
      <el-table-column label="是否开启"  align="center">
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.enable"
            active-color="#13ce66"
            inactive-color="#ff4949"
            @change="goodsEnable(scope.row.goods_no)">
          </el-switch>
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

    <el-dialog :visible.sync="dialogFormVisible" :title="dialogStatus==='create'?'新增用户':'编辑用户'">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-width="80px" label-position="left">
        <el-form-item label="套餐名称" prop="goods_name">
          <el-input v-model="temp.goods_name" placeholder="套餐名称" maxlength="20"/>
        </el-form-item>
        <el-form-item label="套餐价格" prop="goods_price">
          <el-input-number v-model="temp.goods_price" placeholder="套餐价格" :step="0.01" size="medium" controls-position="right" :min="0"/>
        </el-form-item>
        <el-form-item label="是否优惠" prop="discounts">
          <el-switch
            v-model="temp.discounts"
            active-color="#13ce66"
            inactive-color="#ff4949">
          </el-switch>
        </el-form-item>
        <el-form-item label="优惠价格" prop="discounts_goods" v-if="temp.discounts">
          <el-input-number v-model="temp.discounts_goods" placeholder="优惠价格" :step="0.01" size="medium" controls-position="right" :min="0"/>
        </el-form-item>
        <el-form-item label="套餐规格" prop="goods_sku">
          <el-input placeholder="请输入内容" v-model="temp.goods_sku.count_value" class="input-with-select" type="number">
            <el-select v-model="temp.goods_sku.use_type" slot="prepend" placeholder="请选择">
              <el-option label="天" value="day"></el-option>
              <el-option label="周" value="week"></el-option>
              <el-option label="月" value="month"></el-option>
              <el-option label="年" value="year"></el-option>
            </el-select>
          </el-input>
        </el-form-item>
        <el-form-item label="是否开启" prop="enable">
          <el-switch
            v-model="temp.enable"
            active-color="#13ce66"
            inactive-color="#ff4949">
          </el-switch>
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
import { getList, storeGoods, editGoods, enableGoods } from '@/api/paid/paidGoods'
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'

const defaultTemp = {
  goods_no: undefined,
  goods_name: undefined,
  goods_price: 0.00,
  discounts_goods: 0.00,
  is_discounts: 0,
  discounts: false,
  goods_sku: {
    use_type: 'day',
    count_value: 1
  },
  is_enable: 1,
  enable: true
}
export default {
  components: { Pagination },
  data() {
    return {
      rules: {
        goods_name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
        goods_price: [{ required: true, message: '请输入套餐价格', trigger: 'blur' }]
      },
      list: null,
      total: 0,
      listLoading: true,
      listQuery: {
        page: 1,
        per_page: 10,
        goods_name: undefined,
        goods_no: undefined
      },
      enableChanging: false,
      dialogStatus: '',
      dialogFormVisible: false,
      temp: Object.assign({}, defaultTemp)
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      getList(this.listQuery).then(response => {
        const records = response.data.records
        records.forEach(function(item) {
          item.enable = item.is_enable === 1
          item.discounts = item.is_discounts === 1
          item.goods_price = item.goods_price / 100
          item.discounts_goods = item.discounts_goods / 100
        })
        this.list = records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    goodsEnable(goodsNo) {
      if (this.enableChanging) {
        return
      }
      enableGoods(goodsNo).then(response => {
        this.enableChanging = false
      })
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
        goods_no: undefined,
        goods_name: undefined,
        goods_price: 0.00,
        discounts_goods: 0.00,
        is_discounts: 0,
        discounts: false,
        goods_sku: {
          use_type: 'day',
          count_value: 1
        },
        is_enable: 1,
        enable: true
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
          this.temp.is_enable = this.temp.enable ? 1 : 0
          this.temp.is_discounts = this.temp.discounts ? 1 : 0
          this.temp.enable = undefined
          this.temp.discounts = undefined
          this.temp.goods_price *= 100
          this.temp.discounts_goods *= 100
          storeGoods(this.temp).then(response => {
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
      this.temp = Object.assign(defaultTemp, row)
      this.dialogStatus = 'update'
      this.dialogFormVisible = true
    },
    updateData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          this.temp.is_enable = this.temp.enable ? 1 : 0
          this.temp.is_discounts = this.temp.discounts ? 1 : 0
          this.temp.enable = undefined
          this.temp.discounts = undefined
          this.temp.goods_price *= 100
          this.temp.discounts_goods *= 100

          const goodsNo = this.temp.goods_no
          this.temp.goods_no = undefined
          editGoods(goodsNo, this.temp).then(response => {
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

<style>
.el-select .el-input {
  width: 130px;
}
.input-with-select .el-input-group__prepend {
  background-color: #fff;
}
</style>
