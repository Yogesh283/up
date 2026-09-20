import { useEffect, useState } from 'react';
import axios from 'axios';

export default function useApiData(routeName) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const reload = () => {
        setLoading(true);
        setError(null);

        return axios
            .get(route(routeName))
            .then((response) => {
                setData(response.data);
                setError(null);
                return response.data;
            })
            .catch(() => {
                setError('Could not load data. Please try again.');
            })
            .finally(() => {
                setLoading(false);
            });
    };

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError(null);

        axios
            .get(route(routeName))
            .then((response) => {
                if (!cancelled) {
                    setData(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Could not load data. Please try again.');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [routeName]);

    return { data, loading, error, reload, setData };
}
