<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">ID搜索：</span>
        <el-input v-model="search.search_id" placeholder="广告主ID" clearable
                  style="width: 240px"></el-input>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">规则名称：</span>
        <el-input v-model="search.search_name" placeholder="规则名称" clearable
                  style="width: 240px"></el-input>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="24">
        <el-button type="success" icon="el-icon-document-add" @click="addShow"
                   style="float: right; margin-right: 10px;">添加
        </el-button>
        <el-button type="primary" icon="el-icon-search" @click="onSearch" style="float: right; margin-right: 10px;">搜索
        </el-button>
      </el-col>
    </el-row>

    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      :stripe="true"
    >
      <el-table-column align="center" label="ID" width="80">
        <template slot-scope="scope">
          {{ scope.row.rule_id }}
        </template>
      </el-table-column>
      <el-table-column label="规则名称" align="center" width="100">
        <template slot-scope="scope">
          {{ scope.row.name }}
        </template>
      </el-table-column>
      <el-table-column label="广告主" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.advertiser_name }}</div>
          <div><b>ID：{{ scope.row.advertiser_id }}</b></div>
        </template>
      </el-table-column>
      <el-table-column label="规则内容" align="left">
        <template slot-scope="scope">
          <div>周期：[{{ scope.row.cycle_day}}]天内，消耗：[{{ operator_map[scope.row.cost.operator] }}] [{{ scope.row.cost.value }}元]</div>
          <div>阀值（或）：</div>
          <div><span v-show="scope.row.threshold.active_cost.checked == true">激活成本 [{{ operator_map[scope.row.threshold.active_cost.operator] }}] [{{ scope.row.threshold.active_cost.value }}元]</span></div>
          <div><span v-show="scope.row.threshold.convert_cost.checked == true">转化成本 [{{ operator_map[scope.row.threshold.convert_cost.operator] }}] [{{ scope.row.threshold.convert_cost.value }}元]</span></div>
          <div><span v-show="scope.row.threshold.action_rate.checked == true">关键行为率 [{{ operator_map[scope.row.threshold.action_rate.operator] }}] [{{ scope.row.threshold.action_rate.value }}%]</span></div>
          <div><span v-show="scope.row.threshold.cvr.checked == true">CVR [{{ operator_map[scope.row.threshold.cvr.operator] }}] [{{ scope.row.threshold.cvr.value }}%]</span></div>
          <div><span v-show="scope.row.threshold.arpu.checked == true">ARPU [{{ operator_map[scope.row.threshold.arpu.operator] }}] [{{ scope.row.threshold.arpu.value }}元]</span></div>
          <div><span v-show="scope.row.threshold.roi.checked == true">ROI [{{ operator_map[scope.row.threshold.roi.operator] }}] [{{ scope.row.threshold.roi.value }}元]</span></div>

        </template>
      </el-table-column>
      <el-table-column label="执行有效期" align="center">
        <template slot-scope="scope">
          {{ scope.row.start_date }}至{{ scope.row.end_date }}
        </template>
      </el-table-column>
      <el-table-column label="更新时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.updated_time }}
        </template>
      </el-table-column>
      <!-- <el-table-column label="状态" align="center" width="80">
        <template slot-scope="scope">
          <span :style=" 'color:' + (scope.row.status == 1 ? 'green' : 'red')">{{ status_map[scope.row.status] }}</span>
        </template>
      </el-table-column> -->
      <el-table-column align="center" label="操作" width="220">
        <template slot-scope="scope">
          <!-- <el-popconfirm
            :title="'确定' + (scope.row.status == 1 ? '下架' : '上架')+ '此规则吗？'"
            @onConfirm="changeStatus(scope.row.rule_id)"
          >
            <el-button
              slot="reference"
              type="primary"
              size="mini"
              style="margin-right: 10px"
            >{{ (scope.row.status == 1 ? '下架' : '上架') }}
            </el-button>
          </el-popconfirm> -->
          <el-button type="success" size="mini" style="margin-right: 10px" @click="updateShow(scope.$index)">编辑
          </el-button>
          <el-popconfirm
            :title="'确定删除此规则吗？'"
            @onConfirm="deleteData(scope.row.rule_id)"
          >
            <el-button
              slot="reference"
              type="danger"
              size="mini"
            >删除
            </el-button>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
    <pagination
      v-show="total > 0"
      :total="total"
      :page.sync="search.page"
      :limit.sync="search.page_size"
      @pagination="fetchData"
    />

    <el-dialog :visible.sync="save_dialog_visible" :title="save_dialog_title" width="700px">
      <el-form label-position="left" label-width="80px" :model="save_data" ref= "save_data" :rules="save_data_rules">
          <el-form-item label="广告主">
              <el-select v-model="save_data.advertiser_id" prop="advertiser_id" class="filter-item" style="width: 90%;" size="small" @change="changeAdvertiser" :disabled="selectDisable" filterable  clearable>
                  <el-option v-for="item in advertiserOption"  :label="item.advertiser_name" :value="item.advertiser_id"></el-option>
              </el-select>
          </el-form-item>
        <el-form-item label="规则名称">
          <el-input v-model="save_data.name" prop="name" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="广告计划">
              <el-select v-model="save_data.plan_ids" prop="plan_ids" class="filter-item" style="width: 90%;" size="small" filterable multiple collapse-tags clearable>
                  <!-- <el-checkbox v-model="selectAllChecked" @change='selectAll'>全选</el-checkbox> -->
                  <el-option v-for="item in advertiserPlanOption"  :label="item.plan_name" :value="item.plan_id"></el-option>
              </el-select>
          </el-form-item>
        <el-form-item label="周期">
          <el-input-number v-model="save_data.cycle_day" :min="1" prop="cycle_day" style="width: 100px"></el-input-number>
          <span style="margin-left: 10px">天</span>
        </el-form-item>
        <el-form-item label="消耗">
          <el-select v-model="save_data.cost.operator" prop="numValue" style="width: 100px">
            <el-option
              v-for="(value,key) in operator_map"
              :label="value"
              :value="key"
            ></el-option>
          </el-select>
          <el-input-number v-model="save_data.cost.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
          <span style="margin-left: 10px">元</span>

        </el-form-item>
        <el-form-item label="阀值">
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.active_cost.checked" prop="threshold">激活成本</el-checkbox>
            <el-select v-model="save_data.threshold.active_cost.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.active_cost.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">元</span>
          </div>
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.convert_cost.checked" prop="threshold">转化成本</el-checkbox>
            <el-select v-model="save_data.threshold.convert_cost.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.convert_cost.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">元</span>
          </div>
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.action_rate.checked" prop="threshold">关键行为率</el-checkbox>
            <el-select v-model="save_data.threshold.action_rate.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.action_rate.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">%</span>
          </div>
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.cvr.checked" prop="threshold">CVR</el-checkbox>
            <el-select v-model="save_data.threshold.cvr.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.cvr.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">%</span>
          </div>
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.arpu.checked" prop="threshold">ARPU</el-checkbox>
            <el-select v-model="save_data.threshold.arpu.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.arpu.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">元</span>
          </div>
          <div style="margin: 5px 0">
            <el-checkbox v-model="save_data.threshold.roi.checked" prop="threshold">ROI</el-checkbox>
            <el-select v-model="save_data.threshold.roi.operator" style="width: 100px; margin-left: 4px">
              <el-option
                v-for="(value,key) in operator_map"
                :label="value"
                :value="key"
              ></el-option>
            </el-select>
            <el-input-number v-model="save_data.threshold.roi.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">元</span>
          </div>
        </el-form-item>
        <el-form-item label="执行">
          <div style="margin: 5px 0">
            <el-radio v-model="save_data.execute_type" prop="execute_type" label="open">打开计划</el-radio>
            <el-radio v-model="save_data.execute_type" prop="execute_type" label="close">关闭计划</el-radio>
            <el-radio v-model="save_data.execute_type" prop="execute_type" label="empty">拉空</el-radio>
          </div>
          <div style="margin: 5px 0">
            <el-radio v-model="save_data.execute_type" prop="execute_type" label="budget">预算</el-radio>
            <el-select v-model="save_data.execute_value.budget.operator" style="width: 100px">
              <el-option label="上调" value="up"></el-option>
              <el-option label="下调" value="down"></el-option>
            </el-select>
            <el-input-number v-model="save_data.execute_value.budget.value" :precision="2" :controls="false" :min="0.01" style="width: 100px; margin-left: 4px"></el-input-number>
            <span style="margin-left: 10px">%</span>
          </div>
        </el-form-item>
        <el-form-item label="执行回执">
          <el-checkbox-group v-model="save_data.execute_result" prop="execute_result">
            <el-checkbox label="remind">提醒</el-checkbox>
            <el-checkbox label="execute">直接执行</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="执行频率">
          <el-input-number v-model="save_data.execute_times" :min="1" style="width: 100px"></el-input-number>
          <span style="margin-left: 10px">次</span>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer" style="margin-right: 40%">
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="updateSave" :loading="save_loading">确 定</el-button>
      </div>
    </el-dialog>

    <el-dialog :visible.sync="plan_dialog_visible" title="有计划正在执行，是否要替换新规则？" width="400px">
      <div v-for="ul in plan_rule">
        <span><b>{{ul.name}}</b></span>
        <ul v-for="li in ul.plan_ids">
          <li>{{li}}</li>
        </ul>
      </div>
      <div slot="footer" class="dialog-footer" style="margin-right: 40%">
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="updateSaveConfirm">确 定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {getAppCode} from "@/utils/auth"
import Pagination from '@/components/Pagination'
import {Message} from "element-ui"
import {getList, bindPlan, saveData, changeStatus, deleteData} from "@/api/put/plan-rule"
import { getAdvertiser,getAdvertiserPlan } from '@/api/put/common'

export default {
  components: {Pagination},
  data() {
    return {
      plan_dialog_visible:false,
      plan_rule :{},
      advertiserOption:{},
      advertiserPlanOption:{},
      selectAllChecked:false,
      search: {
        app_code: getAppCode(),
        search_id:'',
        search_name:'',
        status:'',
        page: 1,
        page_size: 10
      },
      save_data: {},
      save_data_rules:{
        name: [
            { required: true, message: '请输入', trigger: 'blur' },
            { min: 3, max: 5, message: '长度在 3 到 5 个字符', trigger: 'blur' }
        ],
        advertiser_id: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        plan_ids: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        numValue: [
            { required: true, message: '请输入', trigger: 'blur' },
        ],
        threshold: [
          { type: 'array', required: true, message: '请至少选择一个', trigger: 'change' }
        ],
        execute_type: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        execute_result: [
          { type: 'array', required: true, message: '请至少选择一个', trigger: 'change' }
        ],
      },

      total: 0,
      list: null,
      listLoading: false,
      save_loading: false,
      status_map: {
        '0': '下架',
        '1': '上架'
      },
      operator_map: {
        lt: '小于',
        le: '小于等于',
        eq: '等于',
        gt: '大于',
        ge: '大于等于',
      },
      threshold_item_map: {
        active_cost: '激活成本',
        convert_cost: '转化成本',
        action_rate: '关键行为率',
        cvr: 'CVR',
        arpu: 'ARPU',
        roi: 'ROI'
      },
      execute_type_map: {
        'open': '打开计划',
        'close': '关闭计划',
        'copy': '复制计划',
        'empty': '拉空',
        'budget': '预算'
      },
      execute_result_map: {
        'remind': '提醒',
        'execute': '直接执行'
      },

      selectDisable:false,
      save_dialog_visible: false,
      save_dialog_title: '',

      plan_dialog_visible: false,
    }
  },
  created() {
    this.initSaveData()
    this.onSearch()
    this.getAdvertiser()
  },
  methods: {
    initSaveData() {
      this.advertiserPlanOption={}
      this.save_data = {
        app_code: getAppCode(),
        rule_id: 0,
        advertiser_id:'',
        plan_ids:[],
        confirm :0,
        name: '',
        cycle_day: '1',
        cost: {
          operator: 'ge',
          value: ''
        },
        threshold: {
          active_cost: {
            checked: false,
            operator: 'ge',
            value: ''
          },
          convert_cost: {
            checked: false,
            operator: 'ge',
            value: ''
          },
          action_rate: {
            checked: false,
            operator: 'ge',
            value: ''
          },
          cvr: {
            checked: false,
            operator: 'ge',
            value: ''
          },
          arpu: {
            checked: false,
            operator: 'ge',
            value: ''
          },
          roi: {
            checked: false,
            operator: 'ge',
            value: ''
          }
        },
        execute_type: '',
        execute_value: {
          budget: {
            operator: 'up',
            value: ''
          }
        },
        execute_result: [],
        execute_times: '1'
      }
    },
    onSearch() {
      if (!this.search.app_code) {
        Message({
          message: '请选择应用',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.search.page = 1
      this.fetchData()
    },
    fetchData() {
      this.listLoading = true
      getList(this.search).then(response => {
        this.list = response.data.records
        this.list.map(row => {

        })
        this.total = response.data.total
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    addShow() {
      this.initSaveData()
      this.selectDisable = false
      this.save_dialog_visible = true
      this.save_dialog_title = '添加'
    },
    updateShow(index) {
      this.save_data = _.clone(this.list[index])
      this.getAdvertiserPlan()
      this.selectDisable = true
      this.save_dialog_visible = true
      this.save_dialog_title = '修改'
    },
    updateSaveConfirm(){
      this.save_data.confirm = 1;
      this.updateSave()
    },
    updateSave() {
      this.save_loading = true
      saveData(this.save_data).then(response => {
        if(response.data.length){
          this.save_loading = false
          this.plan_dialog_visible = true
          this.plan_rule = response.data
          return
        }

        Message({
          message: '保存成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.save_dialog_visible = false
        this.plan_dialog_visible = false
        this.save_loading = false
        this.save_dialog_title = ''
        this.onSearch()
      }).catch(() => {
        this.save_loading = false
      })
    },
    deleteData(id) {
      deleteData({id}).then(response => {
        Message({
          message: '删除成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.onSearch()
      })
    },
    changeStatus(id) {
      changeStatus({id}).then(response => {
        Message({
          message: '操作成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.onSearch()
      })
    },
    confirm() {
      this.visible = false
      this.$emit('onConfirm')
    },
    changeAdvertiser(){
      this.getAdvertiserPlan()
      this.save_data.plan_ids = [];
    },
    getAdvertiser() {
        getAdvertiser({'app_code':this.search.app_code}).then(res => {
            this.advertiserOption = res.data.list
        })
    },
    getAdvertiserPlan() {
        getAdvertiserPlan({'app_code':this.search.app_code,'advertiser_id':this.save_data.advertiser_id}).then(res => {
            this.advertiserPlanOption = res.data.list
        })
    },
  }
}
</script>
<style>
.el-row {
  margin-bottom: 10px;
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
