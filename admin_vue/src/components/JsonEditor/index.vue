<template>
  <div class="json-editor">
    <textarea ref="textarea"/>
  </div>
</template>

<script>
import CodeMirror from 'codemirror'
import 'codemirror/addon/lint/lint.css'
import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/rubyblue.css'

require('script-loader!jsonlint')
import 'codemirror/mode/javascript/javascript'
import 'codemirror/addon/lint/lint'
import 'codemirror/addon/lint/json-lint'

export default {
  name: 'JsonEditor',
  /* eslint-disable vue/require-prop-types */
  props: ['value'],
  data() {
    return {
      jsonEditor: false
    }
  },
  watch: {
    value(value) {
      const editorValue = this.jsonEditor.getValue()
      if (value !== editorValue) {
        if (this.value) {
          this.jsonEditor.setValue(JSON.stringify(JSON.parse(this.value), null, 2))
        } else {
          this.jsonEditor.setValue(this.value)
        }
      }
    }
  },
  mounted() {
    // CodeMirror.fromTextArea()中第一个参数是DOM元素，而且必须是textarea元素；第二个参数是可选配置项
    this.jsonEditor = CodeMirror.fromTextArea(this.$refs.textarea, {
      lineNumbers: true,
      mode: 'application/json',
      gutters: ['CodeMirror-lint-markers'],
      theme: 'rubyblue',
      lint: true
    })
    if (this.value) {
      this.jsonEditor.setValue(JSON.stringify(JSON.parse(this.value), null, 2))
    } else {
      this.jsonEditor.setValue(this.value)
    }
    this.jsonEditor.on('change', cm => {
      this.$emit('changed', cm.getValue())
      this.$emit('input', cm.getValue())
    })
  },
  methods: {
    getValue() {
      return this.jsonEditor.getValue()
    }
  }
}
</script>

<style scoped>
.json-editor {
  height: 100%;
  position: relative;
}

.json-editor >>> .CodeMirror {
  height: auto;
  min-height: 300px;
}

.json-editor >>> .CodeMirror-scroll {
  min-height: 300px;
}

.json-editor >>> .cm-s-rubyblue span.cm-string {
  color: #F08047;
}

.addbtn {
  margin-bottom: 15px;
  margin-left: 30px;
}
</style>
