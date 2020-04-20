while getopts ":a:" o; do
    case "${o}" in
        a)
            s=${OPTARG}
            ;;
        *)
            ;;
    esac
done

echo "${s}"
