FILES=Aktual.html Los.html Vysledky.html
soutez=624A2B

default: $(FILES)

clean:
	-rm $(FILES)

%.html:
	curl -o "$@" "http://nv.fotbal.cz/domaci-souteze/kao/souteze.asp?soutez=$(soutez)&show=$(@:.html=)"

.PHONY: default clean

