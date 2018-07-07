docinspire.yaml : start.yaml domains.yaml schemes.yaml objectypes.yaml
	cat start.yaml domains.yaml schemes.yaml objectypes.yaml > docinspire.yaml
domains.yaml : dldomain.php
	php dldomain.php > domains.yaml
schemes.yaml : mkscheme.php codelist.pser enum.pser
	php mkscheme.php > schemes.yaml
codelist.pser : dlcodelist.php
	php dlcodelist.php > /dev/null
enum.pser : dlenum.php
	php dlenum.php > /dev/null
objectypes.yaml : mkot.php sot.pser datatype.pser
	php mkot.php > objectypes.yaml
sot.pser : dlsot.php
	php dlsot.php > /dev/null
datatype.pser : dlmt.php
	php dlmt.php > /dev/null
