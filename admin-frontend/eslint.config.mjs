import { defineConfig, globalIgnores } from "eslint/config"
import nextVitals from "eslint-config-next/core-web-vitals"
import nextTs from "eslint-config-next/typescript"
import customRules from "./eslint-plugin-custom-rules/index.js"
import eslintPluginPrettier from "eslint-plugin-prettier"

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  {
    plugins: {
      "custom-rules": customRules,
      prettier: eslintPluginPrettier,
    },
    rules: {
      "custom-rules/modules-imports": [
        "error",
        {
          srcPath: "src",
          modulesPath: "modules",
          modulesAlias: "@modules",
          exceptModules: ["shared"],
        },
      ],
      "prettier/prettier": "error",
    },
  },
  globalIgnores([
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
    "eslint-plugin-custom-rules/**",
    // Skill assets are templates meant to be copied into a project, not app source.
    "skills/**",
  ]),
])

export default eslintConfig
