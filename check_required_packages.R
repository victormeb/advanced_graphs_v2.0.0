os <- Sys.info()[["sysname"]]

required_packages <- c(
  "rmarkdown",
  "expss",            
  "stringr",
  "Hmisc",
  "RCurl",
  "likert",
  "ggplot2",
  "tidyr",
  "psych",
  "plyr",
  "formattable",
  "leaflet",
  "htmlwidgets",
  "htmltools",
  "igraph"
)

if (os=="Windows") {
  # required_packages <- c(required_packages,"installr")
}


missing_packages <- c()
for (required_package in required_packages) {
  if(!(required_package %in% rownames(installed.packages()))) {
    missing_packages <- c(missing_packages, required_package)
  }
}
if (length(missing_packages)>0) {
  print(paste("Missing package(s):", paste(missing_packages,collapse=", ")))
} else {
  print("Ok")
}